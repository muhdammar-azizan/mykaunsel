# Account pages (profile, security, notifications)

One set of controllers and views (`App\Http\Controllers\Platform\Account*`, `resources/views/platform/account/`), used in two places:

| Where | Address | Route names | Who |
|---|---|---|---|
| Platform Admin | `/platform/account` | `platform.account...` | a signed-in Platform Admin |
| Counselor and organization admin portals | `/portal/account` | `portal.account...` | a signed-in counselor or organization admin (`role:org_admin,counselor`) |

`App\Support\AccountArea` says which one a request is in (from the route name), so a form posts, a redirect returns and a link points to the same area. The pages sit in the layout of the area (`x-account-layout`). Everything else is the same as before: always the signed-in person, never an id from the address; the current password is needed to change it; changing it signs out the other devices; two-factor setup and recovery codes; the profile photo; changing the e-mail address by a signed link sent to the new address.

## E-mails a person can switch off (Account > Notifications)
- **Platform Admin:** their own set (`config/account.php`, `notifications`).
- **Counselor:** *Sessions and time requests* (`counselor_session_emails`): a session booked, moved or cancelled, and a new time request.
- **Organization admin:** *Members* (`org_member_emails`): people waiting for approval, and a member who left.
- Only the keys of the person's own role can be saved; another role's key is refused (422). Essential notices (a suspension, the end of an assignment, an invoice) are not here and are always sent. Whatever sends a switchable e-mail checks `User::wantsNotification($key)` in its `via()`.

## Not built
- Account activity and the audit log link of Platform Admin's menu (portal people see their own recent security activity on the Security tab).
- An "Account" link for people who are only members (they use the app).
