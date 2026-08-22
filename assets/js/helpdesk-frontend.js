/**
 * WP Helpdesk – Customer-facing frontend script
 */
( function ( window, document ) {
	'use strict';

	var config = window.WPHelpdesk || {};
	var restBase = config.restBase || '';
	var restNonce = config.restNonce || '';
	var i18n = config.i18n || {};

	function buildQuery( params ) {
		var searchParams = new window.URLSearchParams();

		Object.keys( params || {} ).forEach( function ( key ) {
			var value = params[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function ( item ) {
					if ( item !== undefined && item !== null && item !== '' ) {
						searchParams.append( key + '[]', item );
					}
				} );
				return;
			}

			if ( value !== undefined && value !== null && value !== '' ) {
				searchParams.append( key, value );
			}
		} );

		return searchParams.toString();
	}

	function apiGet( path ) {
		return window.fetch( restBase + path, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': restNonce }
		} ).then( function ( res ) {
			return res.json();
		} );
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

	function apiUpload( path, formData ) {
		return window.fetch( restBase + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': restNonce },
			body: formData
		} ).then( function ( res ) {
			return res.json().then( function ( data ) {
				data.__status = res.status;
				return data;
			} );
		} );
	}

	function FormController( container, formType ) {
		this.container = container;
		this.formType = formType;
		this.definition = config.formDefinitions && config.formDefinitions[ formType ] ? config.formDefinitions[ formType ] : null;
		this.steps = Array.from( container.querySelectorAll( '.hd-form-step' ) );
		this.stepEls = Array.from( container.querySelectorAll( '.hd-steps .hd-step' ) );
		this.currentStep = 0;
		this.selectedTopicId = 0;
		this.selectedTopicTitle = '';
		this.selectedTopicDescription = '';
		this.topicPath = [];
		this.canContinueFromTopic = false;
		this.storageKey = 'wpHelpdeskForm:' + formType;
		this.sessionToken = this._getSessionToken();
		this.resetCounter = this._loadResetCounter();
		this.pendingState = null;
		this.persistTimer = null;
		this.userOrders = null;

		this._bindEvents();
		this._bindStorageEvent();
		this._loadTopics();
	}

	FormController.prototype._bindEvents = function () {
		var self = this;
		var topicSelect = this.container.querySelector( 'select[name="topic_id"]' );
		var nextBtn0 = this.container.querySelector( '[id$="-step0-next"]' );
		var submitBtn = this.container.querySelector( '[id$="-step1-submit"]' );

		if ( topicSelect ) {
			topicSelect.addEventListener( 'change', function () {
				self._onTopicChange( topicSelect, 0 );
			} );
		}

		if ( nextBtn0 ) {
			nextBtn0.addEventListener( 'click', function () {
				if ( ! self.canContinueFromTopic ) {
					self._showTopicError( i18n.errorCompleteTopic || 'Please complete topic selection.' );
					return;
				}
				self._goToStep( self._getNextStepIndex( 'continue' ) );
			} );
		}

		this.container.querySelectorAll( '[data-action="prev"]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				self._goToStep( self.currentStep - 1 );
			} );
		} );

		this.container.querySelectorAll( '[data-action="start-over"]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				self._startOver();
			} );
		} );

		if ( submitBtn ) {
			submitBtn.addEventListener( 'click', function () {
				self._submitForm( submitBtn );
			} );
		}

		var orderRelationSelect = this.container.querySelector( 'select[name="order_relation"]' );
		if ( orderRelationSelect ) {
			orderRelationSelect.addEventListener( 'change', function () {
				self._onOrderRelationChange();
			} );
		}

		this.container.addEventListener( 'input', function () {
			self._saveState();
		} );
		this.container.addEventListener( 'change', function () {
			self._saveState();
		} );
	};

	/**
	 * Load the persisted reset counter from sessionStorage (default: 0).
	 */
	FormController.prototype._loadResetCounter = function () {
		var raw = window.sessionStorage.getItem( this.storageKey + ':v' );
		return raw !== null ? parseInt( raw, 10 ) || 0 : 0;
	};

	/**
	 * Persist the current reset counter to sessionStorage.
	 */
	FormController.prototype._saveResetCounter = function () {
		window.sessionStorage.setItem( this.storageKey + ':v', String( this.resetCounter ) );
	};

	/**
	 * Listen for storage events from other tabs.
	 * When another tab increments the reset counter, this tab is immediately
	 * hard-reset to step 0 to prevent stale multi-tab state.
	 */
	FormController.prototype._bindStorageEvent = function () {
		var self = this;
		window.addEventListener( 'storage', function ( event ) {
			if ( event.key !== self.storageKey + ':v' ) {
				return;
			}
			var newCounter = event.newValue !== null ? parseInt( event.newValue, 10 ) || 0 : 0;
			if ( newCounter > self.resetCounter ) {
				// Another tab reset the flow: synchronise and return to step 0.
				self.resetCounter = newCounter;
				self._hardReset();
			}
		} );
	};

	/**
	 * Perform a hard client-side reset to step 0 without issuing a server call.
	 * Used when a cross-tab storage event indicates the session was reset elsewhere.
	 */
	FormController.prototype._hardReset = function () {
		this.selectedTopicId = 0;
		this.selectedTopicTitle = '';
		this.selectedTopicDescription = '';
		this.topicPath = [];
		this.canContinueFromTopic = false;
		this.pendingState = null;

		if ( this.persistTimer ) {
			window.clearTimeout( this.persistTimer );
			this.persistTimer = null;
		}

		this.container.querySelectorAll( 'input[name], textarea[name]' ).forEach( function ( el ) {
			if ( ! el.readOnly ) {
				el.value = '';
			}
		} );

		var topicSelect = this.container.querySelector( 'select[name="topic_id"]' );
		if ( topicSelect ) {
			topicSelect.value = '';
		}
		var orderRelationSelect = this.container.querySelector( 'select[name="order_relation"]' );
		if ( orderRelationSelect ) {
			orderRelationSelect.value = '';
		}
		this._hideOrderSelectField();
		var branchContainer = this.container.querySelector( '[data-role="topic-branch"]' );
		if ( branchContainer ) {
			branchContainer.innerHTML = '';
		}
		var hintEl = this.container.querySelector( '[id$="-topic-description"]' );
		if ( hintEl ) {
			hintEl.textContent = '';
		}
		var summaryEl = this.container.querySelector( '[data-role="topic-description-step1"]' );
		if ( summaryEl ) {
			summaryEl.textContent = '';
		}
		this._clearTopicError();
		this._clearError();
		var nextBtn = this.container.querySelector( '[id$="-step0-next"]' );
		if ( nextBtn ) {
			nextBtn.disabled = true;
		}
		this._renderKnowledgeBaseSuggestions( [] );

		// Wipe client storage so this tab is fully clean.
		window.sessionStorage.removeItem( this.storageKey );

		// Navigate to step 0 without calling _saveState to avoid re-persisting.
		if ( this.steps[ this.currentStep ] ) {
			this.steps[ this.currentStep ].classList.add( 'hd-form-step--hidden' );
		}
		if ( this.steps[ 0 ] ) {
			this.steps[ 0 ].classList.remove( 'hd-form-step--hidden' );
		}
		this.stepEls.forEach( function ( el ) {
			el.classList.remove( 'hd-step--done', 'hd-step--active' );
		} );
		if ( this.stepEls[ 0 ] ) {
			this.stepEls[ 0 ].classList.add( 'hd-step--active' );
		}
		this.currentStep = 0;
		window.scrollTo( 0, 0 );
	};

	FormController.prototype._loadTopics = function () {
		var self = this;
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
				opt.value = topic.id;
				opt.textContent = topic.title;
				opt.dataset.description = topic.description || '';
				select.appendChild( opt );
			} );

			self._restoreState();
			if ( ! self._isTopicRequired() ) {
				self.canContinueFromTopic = true;
				var nextBtn = self.container.querySelector( '[id$="-step0-next"]' );
				if ( nextBtn ) {
					nextBtn.disabled = false;
				}
			}
		} ).catch( function () {
			// Ignore topic load failure.
		} );

	};

	/**
	 * Handle order relation selection changes.
	 */
	FormController.prototype._onOrderRelationChange = function ( restoreOrderId ) {
		var orderRelationSelect = this.container.querySelector( 'select[name="order_relation"]' );
		var orderField = this.container.querySelector( '[data-role="order-select-field"]' );
		var orderSelect = this.container.querySelector( 'select[name="order_id"]' );
		var value = orderRelationSelect ? String( orderRelationSelect.value || '' ) : '';
		var self = this;

		if ( value !== 'existing_order_related' ) {
			this._hideOrderSelectField();
			this._clearError();
			return;
		}

		if ( this.formType === 'guest' ) {
			this._hideOrderSelectField();
			this._showError( i18n.errorLoginRequired || 'Please login to create ticket' );
			return;
		}

		// Clear any lingering error (e.g. "Please select an order relation." from a
		// previous submit attempt) so it does not persist while the order list loads.
		this._clearError();

		if ( orderField ) {
			orderField.classList.remove( 'hd-form-step--hidden' );
		}
		if ( orderSelect ) {
			orderSelect.required = true;
			orderSelect.setAttribute( 'aria-required', 'true' );
		}

		if ( Array.isArray( this.userOrders ) ) {
			this._renderOrderSelectOptions( this.userOrders, restoreOrderId );
			return;
		}

		apiGet( 'user-orders' ).then( function ( orders ) {
			self.userOrders = Array.isArray( orders ) ? orders : [];
			self._renderOrderSelectOptions( self.userOrders, restoreOrderId );
		} ).catch( function () {
			self.userOrders = [];
			self._renderOrderSelectOptions( [], restoreOrderId );
		} );
	};

	FormController.prototype._renderOrderSelectOptions = function ( orders, restoreOrderId ) {
		var orderSelect = this.container.querySelector( 'select[name="order_id"]' );
		if ( ! orderSelect ) {
			return;
		}
		orderSelect.innerHTML = '';

		var placeholder = document.createElement( 'option' );
		placeholder.value = '';
		placeholder.textContent = i18n.selectOrderPlaceholder || 'Select #Order';
		placeholder.disabled = true;
		placeholder.selected = true;
		orderSelect.appendChild( placeholder );

		orders.forEach( function ( order ) {
			var opt = document.createElement( 'option' );
			opt.value = String( order.id );
			opt.textContent = '#' + String( order.number );
			orderSelect.appendChild( opt );
		} );

		if ( restoreOrderId ) {
			orderSelect.value = String( restoreOrderId );
		}
	};

	FormController.prototype._hideOrderSelectField = function () {
		var orderField = this.container.querySelector( '[data-role="order-select-field"]' );
		var orderSelect = this.container.querySelector( 'select[name="order_id"]' );
		if ( orderField ) {
			orderField.classList.add( 'hd-form-step--hidden' );
		}
		if ( orderSelect ) {
			orderSelect.required = false;
			orderSelect.setAttribute( 'aria-required', 'false' );
			orderSelect.value = '';
		}
	};

	FormController.prototype._onTopicChange = function ( select, level ) {
		var self = this;
		var val = select.value;
		var nextBtn = this.container.querySelector( '[id$="-step0-next"]' );
		var hintEl = this.container.querySelector( '[id$="-topic-description"]' );
		var branchContainer = this.container.querySelector( '[data-role="topic-branch"]' );

		while ( branchContainer && branchContainer.children.length > level ) {
			branchContainer.removeChild( branchContainer.lastChild );
		}

		this.topicPath = this.topicPath.slice( 0, level );
		this.canContinueFromTopic = false;
		if ( nextBtn ) {
			nextBtn.disabled = true;
		}

		if ( ! val ) {
			this.selectedTopicId = 0;
			this._renderKnowledgeBaseSuggestions( [] );
			if ( this._isTopicRequired() ) {
				this._showTopicError( i18n.errorSelectTopic || 'Please select a topic.' );
			} else {
				this._clearTopicError();
				this.canContinueFromTopic = true;
				if ( nextBtn ) {
					nextBtn.disabled = false;
				}
			}
			this._saveState();
			return;
		}

		var opt = select.options[ select.selectedIndex ];
		this.topicPath[ level ] = parseInt( val, 10 );
		this.selectedTopicId = parseInt( val, 10 );
		this.selectedTopicTitle = opt ? opt.textContent.trim() : '';
		this.selectedTopicDescription = opt && opt.dataset.description ? opt.dataset.description : '';
		this._clearTopicError();
		this._refreshKnowledgeBaseSuggestions();

		if ( level === 0 ) {
			if ( hintEl ) {
				hintEl.textContent = opt && opt.dataset.description ? opt.dataset.description : '';
			}
		} else {
			var fieldHintEl = select.parentNode ? select.parentNode.querySelector( '.hd-followup-topic-description' ) : null;
			if ( fieldHintEl ) {
				fieldHintEl.textContent = opt && opt.dataset.description ? opt.dataset.description : '';
			}
		}

		apiGet( 'topics/' + encodeURIComponent( val ) + '/children' ).then( function ( children ) {
			if ( ! Array.isArray( children ) || children.length === 0 ) {
				self.canContinueFromTopic = true;
				if ( nextBtn ) {
					nextBtn.disabled = false;
				}
				self._tryRestoreStep();
				self._saveState();
				return;
			}

			var childSelect = self._renderChildSelect( children, level + 1 );

			if ( self.pendingState && Array.isArray( self.pendingState.topicPath ) && self.pendingState.topicPath[ level + 1 ] ) {
				childSelect.value = String( self.pendingState.topicPath[ level + 1 ] );
				self._onTopicChange( childSelect, level + 1 );
			}

			self._saveState();
		} ).catch( function () {
			self._showTopicError( i18n.errorLoadTransitions || 'Could not load follow-up topics. Please try again.' );
			self._saveState();
		} );
	};

	FormController.prototype._refreshKnowledgeBaseSuggestions = function () {
		var self = this;
		if ( ! Array.isArray( this.topicPath ) || this.topicPath.length === 0 ) {
			this._renderKnowledgeBaseSuggestions( [] );
			return;
		}

		apiGet( 'kb/suggest?' + buildQuery( {
			topic_path: this.topicPath.slice(),
			limit: 5
		} ) ).then( function ( items ) {
			self._renderKnowledgeBaseSuggestions( Array.isArray( items ) ? items : [] );
		} ).catch( function () {
			self._renderKnowledgeBaseSuggestions( [] );
		} );
	};

	FormController.prototype._renderKnowledgeBaseSuggestions = function ( items ) {
		var container = this.container.querySelector( '[data-role="kb-suggestions"]' );
		if ( ! container ) {
			return;
		}

		container.innerHTML = '';
		if ( ! Array.isArray( items ) || items.length === 0 ) {
			return;
		}

		var title = document.createElement( 'h3' );
		var list = document.createElement( 'ul' );

		title.className = 'hd-form-step__title';
		title.textContent = i18n.kbSuggestionsTitle || 'Helpful articles';
		list.className = 'hd-kb-suggestions__list';

		items.forEach( function ( item ) {
			var listItem = document.createElement( 'li' );
			var excerpt = document.createElement( 'p' );
			var link;

			if ( item.url ) {
				link = document.createElement( 'a' );
				link.href = item.url;
				link.textContent = item.title || '';
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				listItem.appendChild( link );
			} else {
				link = document.createElement( 'span' );
				link.textContent = item.title || '';
				listItem.appendChild( link );
			}

			if ( item.excerpt ) {
				excerpt.className = 'hd-field-hint';
				excerpt.textContent = item.excerpt;
				listItem.appendChild( excerpt );
			}

			list.appendChild( listItem );
		} );

		container.appendChild( title );
		container.appendChild( list );
	};

	FormController.prototype._renderChildSelect = function ( children, level ) {
		var self = this;
		var branchContainer = this.container.querySelector( '[data-role="topic-branch"]' );
		var field = document.createElement( 'div' );
		var label = document.createElement( 'label' );
		var select = document.createElement( 'select' );
		var placeholder = document.createElement( 'option' );

		field.className = 'hd-field';
		label.className = 'hd-label';
		label.textContent = i18n.followupTopicLabel || 'Follow-up topic';
		label.setAttribute( 'for', 'hd-child-' + this.formType + '-' + level );

		select.className = 'hd-select';
		select.required = true;
		select.setAttribute( 'aria-required', 'true' );
		select.id = 'hd-child-' + this.formType + '-' + level;

		placeholder.value = '';
		placeholder.textContent = i18n.selectPlaceholder || 'Select …';
		select.appendChild( placeholder );

		children.forEach( function ( child ) {
			var option = document.createElement( 'option' );
			option.value = String( child.id );
			option.textContent = child.title || ( 'Topic #' + child.id );
			option.dataset.description = child.description || '';
			select.appendChild( option );
		} );

		select.addEventListener( 'change', function () {
			self._onTopicChange( select, level );
		} );

		var hint = document.createElement( 'p' );
		hint.className = 'hd-field-hint hd-followup-topic-description';
		hint.setAttribute( 'aria-live', 'polite' );

		field.appendChild( label );
		field.appendChild( select );
		field.appendChild( hint );
		if ( branchContainer ) {
			branchContainer.appendChild( field );
		}

		return select;
	};

	FormController.prototype._renderTransitionSelect = function ( transitions, level ) {
		var self = this;
		var branchContainer = this.container.querySelector( '[data-role="topic-branch"]' );
		var field = document.createElement( 'div' );
		var label = document.createElement( 'label' );
		var select = document.createElement( 'select' );
		var placeholder = document.createElement( 'option' );

		field.className = 'hd-field';
		label.className = 'hd-label';
		label.textContent = i18n.followupTopicLabel || 'Follow-up topic';
		label.setAttribute( 'for', 'hd-transition-' + this.formType + '-' + level );

		select.className = 'hd-select';
		select.required = true;
		select.setAttribute( 'aria-required', 'true' );
		select.id = 'hd-transition-' + this.formType + '-' + level;

		placeholder.value = '';
		placeholder.textContent = i18n.selectPlaceholder || 'Select …';
		select.appendChild( placeholder );

		transitions.forEach( function ( transition ) {
			var topic = transition.to_topic || {};
			var option = document.createElement( 'option' );
			option.value = String( transition.to_topic_id );
			option.textContent = transition.label || topic.title || ( 'Topic #' + transition.to_topic_id );
			option.dataset.description = topic.description || '';
			select.appendChild( option );
		} );

		select.addEventListener( 'change', function () {
			self._onTopicChange( select, level );
		} );

		var hint = document.createElement( 'p' );
		hint.className = 'hd-field-hint hd-followup-topic-description';
		hint.setAttribute( 'aria-live', 'polite' );

		field.appendChild( label );
		field.appendChild( select );
		field.appendChild( hint );
		if ( branchContainer ) {
			branchContainer.appendChild( field );
		}

		return select;
	};

	FormController.prototype._showTopicError = function ( message ) {
		var topicError = this.container.querySelector( '[id$="-topic-error"]' );
		if ( topicError ) {
			topicError.textContent = message || '';
		}
	};

	FormController.prototype._clearTopicError = function () {
		this._showTopicError( '' );
	};

	FormController.prototype._updateStep1TopicSummary = function () {
		var summaryEl = this.container.querySelector( '[data-role="topic-description-step1"]' );
		if ( ! summaryEl ) {
			return;
		}
		summaryEl.textContent = '';
		if ( this.selectedTopicDescription ) {
			var label = document.createElement( 'p' );
			var text = document.createElement( 'p' );
			label.className = 'hd-topic-description-summary__label';
			label.textContent = this.selectedTopicTitle || ( i18n.topicLabel || 'Topic' );
			text.className = 'hd-topic-description-summary__text';
			text.textContent = this.selectedTopicDescription;
			summaryEl.appendChild( label );
			summaryEl.appendChild( text );
		}
	};

	FormController.prototype._goToStep = function ( index ) {
		if ( index < 0 || index >= this.steps.length ) {
			return;
		}

		this.steps[ this.currentStep ].classList.add( 'hd-form-step--hidden' );
		this.steps[ index ].classList.remove( 'hd-form-step--hidden' );

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
		if ( index === 1 ) {
			this._updateStep1TopicSummary();
		}
		this._saveState();
	};

	FormController.prototype._gatherFields = function () {
		var data = {
			topic_id: this.selectedTopicId,
			topic_path: this.topicPath.slice(),
			form_session_token: this.sessionToken
		};

		this.container.querySelectorAll( 'input[name], textarea[name]' ).forEach( function ( el ) {
			data[ el.name ] = el.value;
		} );

		// Include named select fields only (e.g. order_relation).
		// Branch/child selects rendered by _renderChildSelect have no name attribute
		// and are intentionally excluded by the [name] attribute selector.
		this.container.querySelectorAll( 'select[name]' ).forEach( function ( el ) {
			// Skip the topic_id select (managed separately) and branch selects.
			if ( el.name === 'topic_id' ) {
				return;
			}
			data[ el.name ] = el.value;
		} );

		return data;
	};

	FormController.prototype._validate = function ( data ) {
		var errors = [];
		var topicSelect = this.container.querySelector( 'select[name="topic_id"]' );

		if ( topicSelect && topicSelect.required && ! data.topic_id ) {
			errors.push( 'Please select a topic.' );
		}
		if ( ! data.requester_name || data.requester_name.trim() === '' ) {
			errors.push( 'Please enter your name.' );
		}
		if ( ! data.requester_email || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( data.requester_email ) ) {
			errors.push( 'Please enter a valid email address.' );
		}
		if ( ! data.requester_phone || data.requester_phone.trim() === '' ) {
			errors.push( 'Please enter your phone number.' );
		}
		if ( ! data.order_relation || data.order_relation.trim() === '' ) {
			errors.push( i18n.errorSelectOrderRelation || 'Please select an order relation.' );
		} else if ( this.formType === 'guest' && data.order_relation === 'existing_order_related' ) {
			errors.push( i18n.errorLoginRequired || 'Please login to create ticket' );
		} else if ( this.formType === 'member' && data.order_relation === 'existing_order_related' && ( ! data.order_id || data.order_id.trim() === '' ) ) {
			errors.push( i18n.errorSelectOrder || 'Please select #Order.' );
		}
		if ( ! data.subject || data.subject.trim() === '' ) {
			errors.push( 'Please enter a subject.' );
		}
		if ( ! data.message || data.message.trim() === '' ) {
			errors.push( 'Please enter a message.' );
		}

		return errors;
	};

	FormController.prototype._isTopicRequired = function () {
		if ( this.definition && this.definition.fields && this.definition.fields.topic_id ) {
			return !! this.definition.fields.topic_id.required;
		}

		var topicSelect = this.container.querySelector( 'select[name="topic_id"]' );
		return !! ( topicSelect && topicSelect.required );
	};

	FormController.prototype._getNextStepIndex = function ( action ) {
		var stepMap = this.definition && this.definition.next_step_map ? this.definition.next_step_map[ String( this.currentStep ) ] : null;
		if ( stepMap && typeof stepMap[ action ] === 'number' ) {
			return stepMap[ action ];
		}

		return this.currentStep + 1;
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
		var self = this;
		var data = this._gatherFields();
		var errors = this._validate( data );

		this._clearError();
		if ( errors.length ) {
			this._showError( errors[ 0 ] );
			return;
		}

		submitBtn.disabled = true;
		apiPost( this.formType === 'member' ? 'tickets/member' : 'tickets/guest', data ).then( function ( res ) {
			if ( res.__status >= 200 && res.__status < 300 ) {
				var confirmMsg = self.container.querySelector( '[id$="-confirm-msg"]' );
				if ( confirmMsg && res.ticket_no ) {
					confirmMsg.textContent = 'Your request ' + res.ticket_no + ' has been submitted. We will be in touch via email.';
				}
				// Upload any selected attachments after successful ticket creation.
				if ( res.ticket_id ) {
					self._uploadAttachments( res.ticket_id, res.ticket_link );
				}
				self._goToStep( self._getNextStepIndex( 'submit' ) );
				self._clearState();
			} else {
				if ( res && res.code === 'hd_invalid_topic_path' ) {
					self._logTopicPathValidationError( data, res );
				}
				self._showError( res && res.message ? res.message : 'An error occurred. Please try again.' );
				submitBtn.disabled = false;
			}
		} ).catch( function () {
			self._showError( 'Network error. Please check your connection and try again.' );
			submitBtn.disabled = false;
		} );
	};

	FormController.prototype._logTopicPathValidationError = function ( payload, response ) {
		if ( ! window.console || typeof window.console.warn !== 'function' ) {
			return;
		}

		window.console.warn( '[WP Helpdesk] Ticket topic_path validation failed.', {
			topic_id: payload && payload.topic_id ? payload.topic_id : 0,
			topic_path: payload && Array.isArray( payload.topic_path ) ? payload.topic_path : [],
			reason: response && response.data && response.data.reason ? response.data.reason : null,
			debug: response && response.data && response.data.debug ? response.data.debug : null
		} );
	};

	FormController.prototype._uploadAttachments = function ( ticketId, ticketLink ) {
		var fileInput = this.container.querySelector( 'input[name="attachments[]"]' );
		if ( ! fileInput || ! fileInput.files || fileInput.files.length === 0 ) {
			return;
		}
		var guestToken = '';
		if ( ticketLink ) {
			// Extract guest token from ticket_link URL: /helpdesk/ticket/{no}/{token}/
			var parts = ticketLink.replace( /\/$/, '' ).split( '/' );
			guestToken = parts[ parts.length - 1 ] || '';
		}
		var self  = this;
		var files = Array.prototype.slice.call( fileInput.files );
		files.forEach( function ( file ) {
			var fd = new FormData();
			fd.append( 'file', file );
			if ( guestToken ) {
				fd.append( 'guest_token', guestToken );
			}
			apiUpload( 'tickets/' + ticketId + '/attachments', fd ).catch( function () {
				self._showError( 'One or more attachments could not be uploaded. Please try again.' );
			} );
		} );
	};

	FormController.prototype._getSessionToken = function () {
		var key = this.storageKey + ':token';
		var token = window.sessionStorage.getItem( key );
		if ( token ) {
			return token;
		}
		token = String( Date.now() ) + '-' + Math.random().toString( 16 ).slice( 2 );
		window.sessionStorage.setItem( key, token );
		return token;
	};

	FormController.prototype._saveState = function () {
		var payload = {
			currentStep: this.currentStep,
			resetCounter: this.resetCounter,
			topicPath: this.topicPath.slice(),
			data: this._gatherFields()
		};
		window.sessionStorage.setItem( this.storageKey, JSON.stringify( payload ) );
		this._schedulePersist( payload );
	};

	FormController.prototype._schedulePersist = function ( payload ) {
		var self = this;
		if ( this.persistTimer ) {
			window.clearTimeout( this.persistTimer );
		}
		this.persistTimer = window.setTimeout( function () {
			self._persistSession( payload );
		}, 900 );
	};

	FormController.prototype._restoreState = function () {
		var raw = window.sessionStorage.getItem( this.storageKey );
		if ( ! raw ) {
			return;
		}

		try {
			this.pendingState = JSON.parse( raw );
		} catch ( e ) {
			return;
		}

		// Step guard: if the saved state belongs to a previous reset cycle, discard it.
		var savedCounter = parseInt( this.pendingState.resetCounter || 0, 10 );
		if ( savedCounter !== this.resetCounter ) {
			window.sessionStorage.removeItem( this.storageKey );
			this.pendingState = null;
			return;
		}

		var data = this.pendingState.data || {};
		this.container.querySelectorAll( 'input[name], textarea[name]' ).forEach( function ( field ) {
			if ( Object.prototype.hasOwnProperty.call( data, field.name ) && ! field.readOnly ) {
				field.value = data[ field.name ];
			}
		} );

		// Restore non-topic select fields (e.g. order_relation).
		this.container.querySelectorAll( 'select[name]' ).forEach( function ( sel ) {
			if ( sel.name === 'topic_id' ) {
				return;
			}
			if ( Object.prototype.hasOwnProperty.call( data, sel.name ) ) {
				sel.value = data[ sel.name ];
			}
		} );
		this._onOrderRelationChange( data.order_id || '' );

		var topicPath = Array.isArray( this.pendingState.topicPath ) ? this.pendingState.topicPath : [];
		var topicSelect = this.container.querySelector( 'select[name="topic_id"]' );
		if ( topicSelect && topicPath.length > 0 ) {
			topicSelect.value = String( topicPath[ 0 ] );
			this._onTopicChange( topicSelect, 0 );
		} else {
			this._tryRestoreStep();
		}
	};

	FormController.prototype._tryRestoreStep = function () {
		if ( ! this.pendingState ) {
			return;
		}
		var target = parseInt( this.pendingState.currentStep || 0, 10 );
		var savedCounter = parseInt( this.pendingState.resetCounter || 0, 10 );
		this.pendingState = null;

		// Step guard: only advance past step 0 when the reset counter matches AND
		// the user has a valid topic selected.
		if ( target > 0 && savedCounter === this.resetCounter && this.canContinueFromTopic ) {
			this._goToStep( Math.min( target, 1 ) );
		}
	};

	FormController.prototype._persistSession = function ( payload ) {
		apiPost( 'form-sessions', {
			session_token: this.sessionToken,
			form_type: this.formType,
			step_index: payload.currentStep,
			reset_counter: payload.resetCounter,
			current_topic_id: this.selectedTopicId || 0,
			payload: payload.data,
			topic_path: this.topicPath.slice()
		} ).catch( function () {} );
	};

	FormController.prototype._clearState = function () {
		// Cancel any pending server persist to prevent a stale write after reset.
		if ( this.persistTimer ) {
			window.clearTimeout( this.persistTimer );
			this.persistTimer = null;
		}
		window.sessionStorage.removeItem( this.storageKey );
	};

	FormController.prototype._startOver = function () {
		var self = this;

		// Increment the local reset counter FIRST, before any state is cleared,
		// so that any storage event seen by other tabs carries the new counter.
		this.resetCounter += 1;

		// Reset all branch-dependent client-side state.
		this.selectedTopicId = 0;
		this.selectedTopicTitle = '';
		this.selectedTopicDescription = '';
		this.topicPath = [];
		this.canContinueFromTopic = false;
		this.pendingState = null;

		// Clear form fields.
		this.container.querySelectorAll( 'input[name], textarea[name]' ).forEach( function ( el ) {
			if ( ! el.readOnly ) {
				el.value = '';
			}
		} );

		// Reset the topic select and remove any dynamically added branch selects.
		var topicSelect = this.container.querySelector( 'select[name="topic_id"]' );
		if ( topicSelect ) {
			topicSelect.value = '';
		}

		var orderRelationSelect = this.container.querySelector( 'select[name="order_relation"]' );
		if ( orderRelationSelect ) {
			orderRelationSelect.value = '';
		}
		this._hideOrderSelectField();

		var branchContainer = this.container.querySelector( '[data-role="topic-branch"]' );
		if ( branchContainer ) {
			branchContainer.innerHTML = '';
		}

		// Reset description hint and errors.
		var hintEl = this.container.querySelector( '[id$="-topic-description"]' );
		if ( hintEl ) {
			hintEl.textContent = '';
		}
		var summaryEl = this.container.querySelector( '[data-role="topic-description-step1"]' );
		if ( summaryEl ) {
			summaryEl.textContent = '';
		}
		this._clearTopicError();
		this._clearError();

		// Reset Next button state.
		var nextBtn = this.container.querySelector( '[id$="-step0-next"]' );
		if ( nextBtn ) {
			nextBtn.disabled = true;
		}

		// Clear KB suggestions.
		this._renderKnowledgeBaseSuggestions( [] );

		// Clear persisted client storage (cancels the persist timer as well).
		this._clearState();

		// Persist the new reset counter so other tabs can detect the change.
		this._saveResetCounter();

		// Navigate back to step 0 (saves clean state with the new resetCounter).
		this._goToStep( 0 );

		// Reset step indicator done-states.
		this.stepEls.forEach( function ( el ) {
			el.classList.remove( 'hd-step--done', 'hd-step--active' );
		} );
		if ( this.stepEls[ 0 ] ) {
			this.stepEls[ 0 ].classList.add( 'hd-step--active' );
		}

		// Persist the reset to the server; update local counter from server response
		// so subsequent upserts use the authoritative counter value.
		apiPost( 'form-sessions/restart', {
			session_token: self.sessionToken
		} ).then( function ( res ) {
			if ( res && typeof res.reset_counter === 'number' ) {
				self.resetCounter = res.reset_counter;
				self._saveResetCounter();
				// Re-save clean state with the authoritative counter.
				self._saveState();
			}
		} ).catch( function () {} );
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		var guestContainer = document.getElementById( 'hd-guest-form' );
		var memberContainer = document.getElementById( 'hd-member-form' );

		if ( guestContainer ) {
			new FormController( guestContainer, 'guest' );
		}
		if ( memberContainer ) {
			new FormController( memberContainer, 'member' );
		}
	} );

}( window, document ) );

/* =========================================================================
 * Guest Ticket View – lightbox + reply form
 * ====================================================================== */
( function ( window, document ) {
	'use strict';

	function initLightbox() {
		var lightbox = document.getElementById( 'hd-lightbox' );
		var img      = document.getElementById( 'hd-lightbox-img' );
		var closeBtn = document.getElementById( 'hd-lightbox-close' );
		if ( ! lightbox || ! img || ! closeBtn ) { return; }

		function openLightbox( src, alt ) {
			img.src = src;
			img.alt = alt || '';
			lightbox.hidden = false;
			lightbox.style.display = 'flex';
			closeBtn.focus();
		}

		function closeLightbox() {
			lightbox.hidden = true;
			lightbox.style.display = 'none';
			img.src = '';
		}

		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.hd-attachment__thumb-btn' );
			if ( btn ) { openLightbox( btn.dataset.lightboxSrc || '', btn.dataset.lightboxAlt || '' ); }
		} );

		closeBtn.addEventListener( 'click', closeLightbox );
		lightbox.addEventListener( 'click', function ( e ) {
			if ( e.target === lightbox ) { closeLightbox(); }
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! lightbox.hidden ) { closeLightbox(); }
		} );
	}

	function initGuestReplyForm() {
		var form        = document.getElementById( 'hd-guest-reply-form' );
		if ( ! form ) { return; }

		var submitBtn   = document.getElementById( 'hd-guest-reply-submit' );
		var bodyField   = document.getElementById( 'hd-guest-reply-body' );
		var fileInput   = document.getElementById( 'hd-guest-reply-attachment' );
		var errorEl     = document.getElementById( 'hd-guest-reply-error' );
		var successEl   = document.getElementById( 'hd-guest-reply-success' );
		var ticketNo    = form.dataset.ticketNo || '';
		var guestToken  = form.dataset.guestToken || '';
		var restBase    = ( window.WPHelpdesk && window.WPHelpdesk.restBase ) ? window.WPHelpdesk.restBase : '';
		var restNonce   = ( window.WPHelpdesk && window.WPHelpdesk.restNonce ) ? window.WPHelpdesk.restNonce : '';

		if ( ! submitBtn || ! bodyField || ! errorEl || ! successEl ) { return; }

		function showError( msg ) {
			errorEl.textContent = msg;
		}
		function clearError() {
			errorEl.textContent = '';
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			clearError();
			successEl.textContent = '';
			successEl.classList.add( 'hd-success-message--hidden' );
			var body = bodyField.value.trim();
			if ( '' === body ) {
				showError( 'Please enter a message.' );
				bodyField.focus();
				return;
			}

			submitBtn.disabled = true;
			submitBtn.textContent = 'Sending…';

			fetch( restBase + 'tickets/guest-reply', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': restNonce
				},
				body: JSON.stringify( {
					ticket_no:   ticketNo,
					guest_token: guestToken,
					message:     body
				} )
			} )
				.then( function ( res ) { return res.json().then( function ( data ) { return { status: res.status, data: data }; } ); } )
				.then( function ( r ) {
					if ( r.status < 300 ) {
						var successMsg  = r.data.message || 'Your reply has been sent.';
						var ticketId    = r.data.ticket_id;
						var selectedFiles = ( fileInput && fileInput.files ) ? Array.prototype.slice.call( fileInput.files ) : [];
						if ( selectedFiles.length > 0 && ticketId ) {
							var chain = Promise.resolve();
							selectedFiles.forEach( function ( file ) {
								chain = chain.then( function () {
									var fd = new FormData();
									fd.append( 'file', file );
									fd.append( 'guest_token', guestToken );
									return fetch( restBase + 'tickets/' + ticketId + '/attachments', {
										method: 'POST',
										credentials: 'same-origin',
										headers: { 'X-WP-Nonce': restNonce },
										body: fd
									} ).then( function ( uploadResponse ) {
										if ( ! uploadResponse.ok ) {
											throw new Error( 'upload_failed' );
										}
										return uploadResponse;
									} );
								} );
							} );
							chain.then( function () {
								bodyField.value  = '';
								fileInput.value  = '';
								successEl.textContent = successMsg;
								successEl.classList.remove( 'hd-success-message--hidden' );
								submitBtn.disabled    = false;
								submitBtn.textContent = 'Send reply';
							} ).catch( function () {
								bodyField.value  = '';
								fileInput.value  = '';
								successEl.textContent = successMsg;
								successEl.classList.remove( 'hd-success-message--hidden' );
								showError( 'Your reply was sent but one or more attachments could not be uploaded.' );
								submitBtn.disabled    = false;
								submitBtn.textContent = 'Send reply';
							} );
							return;
						}
						bodyField.value = '';
						if ( fileInput ) { fileInput.value = ''; }
						successEl.textContent = successMsg;
						successEl.classList.remove( 'hd-success-message--hidden' );
					} else {
						showError( ( r.data && r.data.message ) || 'Could not send your reply. Please try again.' );
					}
					submitBtn.disabled = false;
					submitBtn.textContent = 'Send reply';
				} )
				.catch( function () {
					showError( 'A network error occurred. Please try again.' );
					submitBtn.disabled = false;
					submitBtn.textContent = 'Send reply';
				} );
		} );
	}

	function initFilePickers() {
		Array.prototype.slice.call( document.querySelectorAll( '.hd-file-picker' ) ).forEach( function ( picker ) {
			var input = picker.querySelector( 'input[type="file"]' );
			var selection = picker.querySelector( '.hd-file-picker__selection' );
			if ( ! input || ! selection ) {
				return;
			}

			var emptyText = selection.getAttribute( 'data-empty-text' ) || 'No files chosen';
			var updateSelection = function () {
				var fileNames = Array.prototype.slice.call( input.files || [] ).map( function ( file ) {
					return file.name;
				} );
				selection.textContent = fileNames.length ? fileNames.join( ', ' ) : emptyText;
			};

			input.addEventListener( 'change', updateSelection );
			updateSelection();
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initLightbox();
		initFilePickers();
		initGuestReplyForm();
	} );

}( window, document ) );
