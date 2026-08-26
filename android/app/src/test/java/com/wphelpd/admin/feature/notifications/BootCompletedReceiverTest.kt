package com.wphelpd.admin.feature.notifications

import android.content.Intent
import com.google.common.truth.Truth.assertThat
import org.junit.Test

class BootCompletedReceiverTest {

    @Test
    fun shouldRescheduleNotificationPolling_acceptsBootCompleted() {
        assertThat(shouldRescheduleNotificationPolling(Intent.ACTION_BOOT_COMPLETED)).isTrue()
    }

    @Test
    fun shouldRescheduleNotificationPolling_acceptsQuickBoot() {
        assertThat(shouldRescheduleNotificationPolling("android.intent.action.QUICKBOOT_POWERON")).isTrue()
    }

    @Test
    fun shouldRescheduleNotificationPolling_rejectsOtherActions() {
        assertThat(shouldRescheduleNotificationPolling(Intent.ACTION_MY_PACKAGE_REPLACED)).isFalse()
        assertThat(shouldRescheduleNotificationPolling(null)).isFalse()
    }
}
