/**
 * WP Helpdesk – Customer-facing frontend script
 *
 * Handles:
 *  - Loading topics into dropdowns via REST
 *  - Topic selection state + description hint
 *  - Multi-step form navigation (next/back)
 *  - Form submission (guest and member flows)
 */
( function ( window, document ) {
	'use strict';

	var config = window.WPHelpdesk || {};
	var restBase  = config.restBase  || '';
	var restNonce = config.restNonce || '';

	/* -----------------------------------------------------------------------
	 * Utility helpers
	 * --------------------------------------------------------------------- */

	function apiGet( path ) {
		return window.fetch( restBase + path, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': restNonce }
		} ).then( function ( res ) { return res.json(); } );
	}

	function apiPost( path, body ) {
		return window.fetch( restBase + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restNonce
			},
			body: JSON.stringify( body )
		} ).then( function ( res ) {
			return res.json().then( function ( data ) {
				data.__status = res.status;
				return data;
			} );
		} );
	}

	/* -----------------------------------------------------------------------
	 * Generic multi-step form controller
	 *
	 * @param {HTMLElement} container  Root .hd-form-container element
	 * @param {string}      formType   'guest' | 'member'
	 * --------------------------------------------------------------------- */

	function FormController( container, formType ) {
		this.container = container;
		this.formType  = formType;
		this.steps     = Array.from( container.querySelectorAll( '.hd-form-step' ) );
		this.stepEls   = Array.from( container.querySelectorAll( '.hd-steps .hd-step' ) );
		this.currentStep = 0;

		// Topic state
		this.selectedTopicId    = 0;
		this.selectedTopicTitle = '';

		this._bindEvents();
		this._loadTopics();
	}

	FormController.prototype._bindEvents = function () {
		var self = this;

		// Topic select change
		var topicSelect = this.container.querySelector( 'select[name="topic_id"]' );
		if ( topicSelect ) {
			topicSelect.addEventListener( 'change', function () {
				self._onTopicChange( topicSelect );
			} );
		}

		// Step 0 "Continue" button
		var nextBtn0 = this.container.querySelector( '[id$="-step0-next"]' );
		if ( nextBtn0 ) {
			nextBtn0.addEventListener( 'click', function () {
				self._goToStep( 1 );
			} );
		}

		// "Back" buttons (data-action="prev")
		var backBtns = this.container.querySelectorAll( '[data-action="prev"]' );
		backBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				self._goToStep( self.currentStep - 1 );
			} );
		} );

		// Submit button
		var submitBtn = this.container.querySelector( '[id$="-step1-submit"]' );
		if ( submitBtn ) {
			submitBtn.addEventListener( 'click', function () {
				self._submitForm( submitBtn );
			} );
		}
	};

	FormController.prototype._loadTopics = function () {
		var self   = this;
		var select = this.container.querySelector( 'select[name="topic_id"]' );
		if ( ! select ) {
			return;
		}

		apiGet( 'topics' ).then( function ( topics ) {
			if ( ! Array.isArray( topics ) ) {
				return;
			}

			topics.forEach( function ( topic ) {
				var opt = document.createElement( 'option' );
				opt.value       = topic.id;
				opt.textContent = topic.title;
				opt.dataset.description = topic.description || '';
				select.appendChild( opt );
			} );
		} ).catch( function () {
			// Silently ignore topic load errors; the field just stays empty.
		} );
	};

	FormController.prototype._onTopicChange = function ( select ) {
		var val       = select.value;
		var nextBtn   = this.container.querySelector( '[id$="-step0-next"]' );
		var hintEl    = this.container.querySelector( '[id$="-topic-description"]' );

		if ( val ) {
			var opt = select.options[ select.selectedIndex ];
			this.selectedTopicId    = parseInt( val, 10 );
			this.selectedTopicTitle = opt ? opt.textContent.trim() : '';

			if ( hintEl ) {
				hintEl.textContent = ( opt && opt.dataset.description ) ? opt.dataset.description : '';
			}
			if ( nextBtn ) {
				nextBtn.disabled = false;
			}
		} else {
			this.selectedTopicId    = 0;
			this.selectedTopicTitle = '';
			if ( hintEl ) {
				hintEl.textContent = '';
			}
			if ( nextBtn ) {
				nextBtn.disabled = true;
			}
		}
	};

	FormController.prototype._goToStep = function ( index ) {
		if ( index < 0 || index >= this.steps.length ) {
			return;
		}

		// Hide current, show target
		this.steps[ this.currentStep ].classList.add( 'hd-form-step--hidden' );
		this.steps[ index ].classList.remove( 'hd-form-step--hidden' );

		// Update step indicator
		if ( this.stepEls[ this.currentStep ] ) {
			this.stepEls[ this.currentStep ].classList.remove( 'hd-step--active' );
			if ( index > this.currentStep ) {
				this.stepEls[ this.currentStep ].classList.add( 'hd-step--done' );
			}
		}
		if ( this.stepEls[ index ] ) {
			this.stepEls[ index ].classList.remove( 'hd-step--done' );
			this.stepEls[ index ].classList.add( 'hd-step--active' );
		}

		this.currentStep = index;
		window.scrollTo( 0, 0 );
	};

	FormController.prototype._gatherFields = function () {
		var data = { topic_id: this.selectedTopicId };

		var fields = this.container.querySelectorAll( 'input[name], textarea[name]' );
		fields.forEach( function ( el ) {
			data[ el.name ] = el.value;
		} );

		return data;
	};

	FormController.prototype._validate = function ( data ) {
		var errors = [];

		if ( ! data.topic_id ) {
			errors.push( 'Please select a topic.' );
		}

		if ( this.formType === 'guest' ) {
			if ( ! data.requester_name || data.requester_name.trim() === '' ) {
				errors.push( 'Please enter your name.' );
			}
			if ( ! data.requester_email || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( data.requester_email ) ) {
				errors.push( 'Please enter a valid email address.' );
			}
		}

		if ( ! data.subject || data.subject.trim() === '' ) {
			errors.push( 'Please enter a subject.' );
		}

		if ( ! data.message || data.message.trim() === '' ) {
			errors.push( 'Please enter a message.' );
		}

		return errors;
	};

	FormController.prototype._showError = function ( msg ) {
		var errEl = this.container.querySelector( '[id$="-form-error"]' );
		if ( errEl ) {
			errEl.textContent = msg;
		}
	};

	FormController.prototype._clearError = function () {
		var errEl = this.container.querySelector( '[id$="-form-error"]' );
		if ( errEl ) {
			errEl.textContent = '';
		}
	};

	FormController.prototype._submitForm = function ( submitBtn ) {
		var self   = this;
		var data   = this._gatherFields();
		var errors = this._validate( data );

		this._clearError();

		if ( errors.length ) {
			this._showError( errors[ 0 ] );
			return;
		}

		submitBtn.disabled = true;

		var endpoint = this.formType === 'member' ? 'tickets/member' : 'tickets/guest';

		apiPost( endpoint, data ).then( function ( res ) {
			if ( res.__status >= 200 && res.__status < 300 ) {
				// Show confirmation step
				var confirmMsg = self.container.querySelector( '[id$="-confirm-msg"]' );
				if ( confirmMsg && res.ticket_no ) {
					confirmMsg.textContent =
						'Your request ' + res.ticket_no + ' has been submitted. We will be in touch via email.';
				}
				self._goToStep( 2 );
			} else {
				var msg = ( res && res.message ) ? res.message : 'An error occurred. Please try again.';
				self._showError( msg );
				submitBtn.disabled = false;
			}
		} ).catch( function () {
			self._showError( 'Network error. Please check your connection and try again.' );
			submitBtn.disabled = false;
		} );
	};

	/* -----------------------------------------------------------------------
	 * Initialisation
	 * --------------------------------------------------------------------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		var guestContainer  = document.getElementById( 'hd-guest-form' );
		var memberContainer = document.getElementById( 'hd-member-form' );

		if ( guestContainer ) {
			new FormController( guestContainer, 'guest' );
		}

		if ( memberContainer ) {
			new FormController( memberContainer, 'member' );
		}
	} );

}( window, document ) );
