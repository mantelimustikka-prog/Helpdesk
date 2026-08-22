# WP HelpD Helpdesk Plugin – First Implementation Checklist

This checklist turns the initial API contract into concrete plugin work items.

## 1. Core Plugin Structure

- [ ] Create or verify a dedicated REST API namespace: `/wp-json/helpdesk/v1/admin/`
- [ ] Add controller classes for admin endpoints
- [ ] Organize code by resource:
  - [ ] `Tickets_Controller`
  - [ ] `Auth_Controller`
  - [ ] `Users_Controller`
  - [ ] `Attachments_Controller`
- [ ] Ensure all controllers register routes on plugin initialization

## 2. Authentication and Authorization

- [ ] Support WordPress Application Password authentication for Android app access
- [ ] Verify authenticated user on every protected endpoint
- [ ] Add capability checks for admin operations
- [ ] Return `401` for unauthenticated requests
- [ ] Return `403` for authenticated users without permission
- [ ] Preserve `X-WP-Nonce` support for browser-based admin requests where needed
- [ ] Define a reusable permission callback for admin routes

## 3. Auth / Session Check Endpoint

- [ ] Implement `GET /auth/check`
- [ ] Return authenticated user identity data
- [ ] Include user roles/capabilities needed by the app
- [ ] Return a consistent error structure for invalid access

## 4. Ticket List Endpoint

- [ ] Implement `GET /tickets`
- [ ] Support filters:
  - [ ] `status`
  - [ ] `search`
  - [ ] `page`
  - [ ] `per_page`
  - [ ] `assigned_to`
- [ ] Return pagination metadata
- [ ] Include the minimum ticket fields required by the app
- [ ] Optimize queries for mobile refresh usage

## 5. Ticket Detail Endpoint

- [ ] Implement `GET /tickets/{id}`
- [ ] Return ticket metadata
- [ ] Return customer data
- [ ] Return message thread
- [ ] Return attachments
- [ ] Handle missing ticket IDs with `404`

## 6. Reply Endpoint

- [ ] Implement `POST /tickets/{id}/reply`
- [ ] Accept message body
- [ ] Support optional internal note flag
- [ ] Return created message ID and timestamp
- [ ] Validate ticket existence before inserting reply

## 7. Ticket Status Endpoint

- [ ] Implement `POST /tickets/{id}/status`
- [ ] Validate allowed status values:
  - [ ] `new`
  - [ ] `open`
  - [ ] `pending`
  - [ ] `resolved`
  - [ ] `closed`
- [ ] Update ticket state safely
- [ ] Return updated status
- [ ] Reject invalid values with `422` or `400`

## 8. Internal Notes Endpoint

- [ ] Implement `POST /tickets/{id}/note`
- [ ] Store notes as private/internal-only entries
- [ ] Ensure notes do not appear in customer-visible replies
- [ ] Return created note ID and ticket ID

## 9. Create Ticket Endpoint

- [ ] Implement `POST /tickets`
- [ ] Accept:
  - [ ] subject
  - [ ] message
  - [ ] customer_name
  - [ ] customer_email
  - [ ] priority
- [ ] Validate required fields
- [ ] Return new ticket ID and ticket number

## 10. Users / Agents Endpoint

- [ ] Implement `GET /users`
- [ ] Return assignable agents/admin users
- [ ] Limit results to users the current admin may assign
- [ ] Keep payload lightweight for mobile use

## 11. Attachments Support

- [ ] Implement `GET /attachments/{id}`
- [ ] Return attachment metadata
- [ ] Confirm access permissions before exposing attachment URLs
- [ ] Ensure private uploads are protected if needed

## 12. Response Format Standardization

- [ ] Normalize success response structure
- [ ] Normalize error response structure
- [ ] Use consistent keys across endpoints
- [ ] Document date/time format as ISO 8601

## 13. Data Model Preparation

- [ ] Confirm ticket storage model in the plugin database
- [ ] Confirm message storage model
- [ ] Confirm attachment association model
- [ ] Confirm status enum or status constants
- [ ] Confirm assignment/user relation storage

## 14. Validation Rules

- [ ] Validate request payloads for all write endpoints
- [ ] Sanitize all text inputs
- [ ] Ensure email validation for customer email fields
- [ ] Prevent invalid status transitions if rules exist

## 15. Performance Considerations

- [ ] Add indexes or efficient queries for list endpoints
- [ ] Paginate all list responses
- [ ] Avoid loading full message histories in the list endpoint
- [ ] Keep response payloads small for mobile clients

## 16. Testing Checklist

- [ ] Test authentication success and failure
- [ ] Test permissions for admin and non-admin users
- [ ] Test each endpoint with valid and invalid payloads
- [ ] Test pagination and filters for ticket list
- [ ] Test reply creation
- [ ] Test status update
- [ ] Test note creation
- [ ] Test create ticket flow
- [ ] Test attachment access permissions

## 17. Android App Integration Readiness

- [ ] Confirm endpoint URLs match the Android contract
- [ ] Confirm field names match expected mobile models
- [ ] Confirm error responses can be handled by the app
- [ ] Confirm auth method works with Android Application Passwords

## 18. MVP Completion Criteria

The plugin API is ready for first Android app integration when:
- [ ] all required endpoints exist
- [ ] auth and permission checks are enforced
- [ ] ticket list and detail responses are stable
- [ ] reply and status actions work reliably
- [ ] create ticket and notes work reliably
- [ ] basic tests pass

## 19. Next Step

After completing this checklist, implement the Android app screens against the live API contract.
