package com.wphelpd.admin.res

import com.google.common.truth.Truth.assertThat
import org.junit.Test
import java.io.File

/**
 * Verifies that all Android resource files required after the PR #101 launcher/splash asset
 * refactor are present on disk and non-empty.
 *
 * These are pure JVM tests that inspect the source tree directly, so they run without a device
 * or emulator and provide fast build-time feedback for missing-resource regressions.
 */
class AndroidResourceWiringTest {

    private val resRoot: File = run {
        // Resolve relative to the test class location so it works from any working directory.
        val classFile = File(
            AndroidResourceWiringTest::class.java.protectionDomain.codeSource.location.toURI()
        )
        // Build output is typically …/app/build/intermediates/… or …/app/build/tmp/…;
        // walk up until we find the "app" module directory, then navigate to src/main/res.
        var dir: File = classFile
        while (dir.name != "app" && dir.parentFile != null) {
            dir = dir.parentFile
        }
        check(dir.name == "app") {
            "Could not locate the 'app' module directory starting from ${classFile.absolutePath}. " +
                "Ensure this test is run from within the Android 'app' module."
        }
        File(dir, "src/main/res")
    }

    companion object {
        private val MIPMAP_DENSITIES = listOf(
            "mipmap-mdpi", "mipmap-hdpi", "mipmap-xhdpi", "mipmap-xxhdpi", "mipmap-xxxhdpi"
        )
        private val DRAWABLE_DENSITIES = listOf(
            "drawable-mdpi", "drawable-hdpi", "drawable-xhdpi", "drawable-xxhdpi", "drawable-xxxhdpi"
        )
    }

    // -------------------------------------------------------------------------
    // Adaptive-icon XML files
    // -------------------------------------------------------------------------

    @Test
    fun `ic_launcher adaptive icon xml exists`() {
        assertFileExists("mipmap-anydpi-v26/ic_launcher.xml")
    }

    @Test
    fun `ic_launcher_round adaptive icon xml exists`() {
        assertFileExists("mipmap-anydpi-v26/ic_launcher_round.xml")
    }

    @Test
    fun `ic_launcher_foreground drawable xml exists`() {
        assertFileExists("drawable/ic_launcher_foreground.xml")
    }

    @Test
    fun `ic_launcher_background drawable xml exists`() {
        assertFileExists("drawable/ic_launcher_background.xml")
    }

    // -------------------------------------------------------------------------
    // ic_launcher_source mipmap density variants
    // -------------------------------------------------------------------------

    @Test
    fun `ic_launcher_source present in all mipmap density buckets`() {
        for (density in MIPMAP_DENSITIES) {
            assertFileExists("$density/ic_launcher_source.png")
        }
    }

    // -------------------------------------------------------------------------
    // Legacy ic_launcher + ic_launcher_round PNGs (pre-v26 fallback)
    // -------------------------------------------------------------------------

    @Test
    fun `ic_launcher png present in all mipmap density buckets`() {
        for (density in MIPMAP_DENSITIES) {
            assertFileExists("$density/ic_launcher.png")
        }
    }

    @Test
    fun `ic_launcher_round png present in all mipmap density buckets`() {
        for (density in MIPMAP_DENSITIES) {
            assertFileExists("$density/ic_launcher_round.png")
        }
    }

    // -------------------------------------------------------------------------
    // Splash / background image density variants
    // -------------------------------------------------------------------------

    @Test
    fun `splash_background jpg present in all drawable density buckets`() {
        for (density in DRAWABLE_DENSITIES) {
            assertFileExists("$density/splash_background.jpg")
        }
    }

    // -------------------------------------------------------------------------
    // XML content sanity checks
    // -------------------------------------------------------------------------

    @Test
    fun `ic_launcher_foreground xml references ic_launcher_source mipmap`() {
        assertFileExists("drawable/ic_launcher_foreground.xml")
        val content = File(resRoot, "drawable/ic_launcher_foreground.xml").readText()
        assertThat(content).contains("@mipmap/ic_launcher_source")
    }

    @Test
    fun `ic_launcher adaptive icon xml references correct drawables`() {
        assertFileExists("mipmap-anydpi-v26/ic_launcher.xml")
        val content = File(resRoot, "mipmap-anydpi-v26/ic_launcher.xml").readText()
        assertThat(content).contains("@drawable/ic_launcher_background")
        assertThat(content).contains("@drawable/ic_launcher_foreground")
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private fun assertFileExists(relativePath: String) {
        val file = File(resRoot, relativePath)
        assertThat(file.exists()).isTrue()
        assertThat(file.length()).isGreaterThan(0L)
    }
}
