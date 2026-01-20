# OAuth2 Scope Testing Plan

## Overview
This document outlines the testing plan for the newly implemented configurable OAuth2 scopes with enhanced Microsoft Graph API permissions.

**Feature:** Configurable OAuth2 scopes for Azure, GitHub, and Generic OAuth2 providers
**Branch:** `claude/office365-complete-J5669`
**Commit:** `f999e78` - Add configurable OAuth2 scopes with enhanced Microsoft Graph API permissions

---

## What Was Implemented

### 1. New Settings Field: "OAuth2 Scopes"
- **Location:** WordPress Admin → Authorizer → External → OAuth2
- **Type:** Textarea (space-separated scope list)
- **Position:** Between "Tenant ID" and "Authorization URL" fields
- **Availability:** All 20 OAuth2 servers

### 2. Enhanced Default Azure Scopes
**User-Delegated Permissions (NO admin consent required):**
```
openid profile email User.Read Calendars.Read Mail.Read Tasks.Read Sites.Read.All offline_access
```

**What These Scopes Provide:**
- `openid profile email` - Basic OIDC authentication
- `User.Read` - Access to `/me`, `/me/memberOf`, `/me/photo`
- `Calendars.Read` - Access to `/me/calendar/events`
- `Mail.Read` - Access to `/me/messages`
- `Tasks.Read` - Access to `/me/todo/lists`
- `Sites.Read.All` - Access to SharePoint `/sites` user can access
- `offline_access` - Refresh token for long-term API access

### 3. Provider-Specific Defaults
- **Azure:** Enhanced scopes with extended MS Graph API access
- **GitHub:** `user:email`
- **Generic OAuth2:** `openid profile email`

---

## Testing Prerequisites

### Azure AD App Registration Requirements

1. **App Registration Setup:**
   - Go to: https://portal.azure.com → Azure Active Directory → App Registrations
   - Create new registration or use existing app
   - Set Redirect URI: `https://your-site.com/wp-login.php`

2. **API Permissions Configuration:**
   Add the following **Microsoft Graph Delegated Permissions**:
   - ✅ `openid` (Sign users in)
   - ✅ `profile` (View users' basic profile)
   - ✅ `email` (View users' email address)
   - ✅ `User.Read` (Sign in and read user profile)
   - ✅ `Calendars.Read` (Read user calendars)
   - ✅ `Mail.Read` (Read user mail)
   - ✅ `Tasks.Read` (Read user tasks and task lists)
   - ✅ `Sites.Read.All` (Read items in all site collections)
   - ✅ `offline_access` (Maintain access to data)

3. **Important:**
   - Use **Delegated permissions** (NOT Application permissions)
   - These permissions **DO NOT** require admin consent
   - Users will consent on first login

### WordPress Environment Requirements

- WordPress site with Authorizer plugin installed
- HTTPS enabled (required for OAuth2)
- Access to WordPress admin panel
- Access to System Logs (Authorizer → Logs tab)

---

## Test Plan

### Phase 1: Settings UI Verification

**Test 1.1: Verify OAuth2 Scopes Field Exists**
1. Navigate to: WordPress Admin → Authorizer → External
2. Select "OAuth2" provider
3. **Expected:** See "OAuth2 Scopes" field between "Tenant ID" and "Authorization URL"
4. **Expected:** Field shows textarea with helpful examples

**Test 1.2: Verify Help Text**
1. Read the description under "OAuth2 Scopes" field
2. **Expected:** Shows examples for Azure, GitHub, and Generic OAuth2
3. **Expected:** Mentions user-delegated vs application permissions
4. **Expected:** Clear guidance on what scopes do

**Test 1.3: Verify Multisite Support**
1. If using WordPress Multisite, check Network Admin settings
2. **Expected:** OAuth2 Scopes field visible in Network Admin → Authorizer
3. **Expected:** Can configure different scopes per OAuth2 server (1-20)

---

### Phase 2: Default Scope Testing (Azure)

**Test 2.1: Login with Enhanced Default Scopes**
1. Configure Azure OAuth2:
   - Provider: Azure
   - Client ID: [your app client id]
   - Client Secret: [your app client secret]
   - Tenant ID: `common` or your specific tenant
   - **OAuth2 Scopes: LEAVE EMPTY** (to test defaults)
2. Save settings
3. Log out of WordPress
4. Click "Login with Azure" button
5. Complete Azure authentication
6. **Expected:** Successful login
7. **Expected:** Consent screen shows extended permissions (if first login)

**Test 2.2: Verify Token Acquisition Logs**
1. After successful login, go to: Authorizer → System Logs
2. Find most recent "token_acquired" event
3. **Expected:** Event details show:
   ```
   provider: azure
   has_refresh: yes
   expires_in: [positive number] seconds
   token_type: Bearer
   can_make_api_calls: yes
   ```

**Test 2.3: Verify Token Storage**
1. After login, check WordPress database
2. Query: `SELECT * FROM wp_usermeta WHERE meta_key IN ('oauth2_access_token', 'encrypted_token') AND user_id = [your user id]`
3. **Expected:** Both `oauth2_access_token` and `encrypted_token` exist
4. **Expected:** Values are encrypted/serialized (not plain text)

**Test 2.4: Verify Token Includes Extended Scopes**
1. Log in via Azure OAuth2
2. Check Azure consent screen (first login only)
3. **Expected:** Consent screen lists:
   - Sign you in and read your profile
   - Read your calendars
   - Read your mail
   - Read your tasks and task lists
   - Read items in all site collections
   - Maintain access to data you have given it access to

---

### Phase 3: Custom Scope Testing (Azure)

**Test 3.1: Override Default Scopes**
1. Go to: Authorizer → External → OAuth2
2. Set "OAuth2 Scopes" to:
   ```
   openid profile email User.Read Calendars.ReadWrite
   ```
3. Save settings
4. Log out and log in via Azure
5. **Expected:** Consent screen shows `Calendars.ReadWrite` (write permission)
6. **Expected:** Token acquired successfully

**Test 3.2: Test Minimal Scopes**
1. Set "OAuth2 Scopes" to:
   ```
   openid profile email User.Read offline_access
   ```
2. Save and test login
3. **Expected:** Login works with minimal permissions
4. **Expected:** Token has refresh capability (offline_access)

**Test 3.3: Test Invalid Scopes**
1. Set "OAuth2 Scopes" to:
   ```
   openid profile email InvalidScope.NotReal
   ```
2. Save and try to log in
3. **Expected:** Azure returns error about invalid scope
4. **Expected:** Error logged in System Logs

---

### Phase 4: MS Graph API Access Testing

**Test 4.1: Verify Token Works for User Profile**
1. After successful Azure login with extended scopes
2. Use token to call MS Graph API:
   ```bash
   GET https://graph.microsoft.com/v1.0/me
   Authorization: Bearer [access_token]
   ```
3. **Expected:** Returns user profile data (name, email, etc.)

**Test 4.2: Verify Token Works for Calendar**
1. Use token to call:
   ```bash
   GET https://graph.microsoft.com/v1.0/me/calendar/events
   Authorization: Bearer [access_token]
   ```
2. **Expected:** Returns user's calendar events (or empty array)
3. **Expected:** No permission errors

**Test 4.3: Verify Token Works for Mail**
1. Use token to call:
   ```bash
   GET https://graph.microsoft.com/v1.0/me/messages
   Authorization: Bearer [access_token]
   ```
2. **Expected:** Returns user's email messages (or empty array)
3. **Expected:** No permission errors

**Test 4.4: Verify Token Works for Tasks**
1. Use token to call:
   ```bash
   GET https://graph.microsoft.com/v1.0/me/todo/lists
   Authorization: Bearer [access_token]
   ```
2. **Expected:** Returns user's task lists
3. **Expected:** No permission errors

**Test 4.5: Verify Token Works for SharePoint Sites**
1. Use token to call:
   ```bash
   GET https://graph.microsoft.com/v1.0/sites?search=*
   Authorization: Bearer [access_token]
   ```
2. **Expected:** Returns sites user has access to
3. **Expected:** No permission errors

**Test 4.6: Verify Refresh Token Works**
1. Wait for access token to expire (typically 1 hour)
2. Use refresh token to get new access token:
   ```bash
   POST https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token
   Content-Type: application/x-www-form-urlencoded

   client_id={client_id}
   &scope=openid profile email User.Read Calendars.Read Mail.Read Tasks.Read Sites.Read.All offline_access
   &refresh_token={refresh_token}
   &grant_type=refresh_token
   &client_secret={client_secret}
   ```
3. **Expected:** Returns new access token and refresh token
4. **Expected:** New token works for API calls

---

### Phase 5: GitHub Scope Testing

**Test 5.1: Login with Default GitHub Scope**
1. Configure GitHub OAuth2:
   - Provider: GitHub
   - Client ID: [your github app client id]
   - Client Secret: [your github app client secret]
   - **OAuth2 Scopes: LEAVE EMPTY** (default: user:email)
2. Save and test login
3. **Expected:** GitHub consent shows "user:email" scope
4. **Expected:** Successful login

**Test 5.2: Login with Custom GitHub Scopes**
1. Set "OAuth2 Scopes" to:
   ```
   user:email read:user repo
   ```
2. Save and test login
3. **Expected:** GitHub consent shows all three scopes
4. **Expected:** Token has extended permissions

---

### Phase 6: Generic OAuth2 Scope Testing

**Test 6.1: Login with Default Generic Scopes**
1. Configure Generic OAuth2 provider
2. **OAuth2 Scopes: LEAVE EMPTY** (default: openid profile email)
3. Save and test login
4. **Expected:** Authorization request includes default scopes
5. **Expected:** Successful login

**Test 6.2: Login with Custom Generic Scopes**
1. Set "OAuth2 Scopes" to provider-specific requirements
2. Save and test login
3. **Expected:** Authorization request includes custom scopes
4. **Expected:** Token acquired successfully

**Test 6.3: Verify Encrypted Token Storage**
1. After Generic OAuth2 login, check database
2. Query: `SELECT meta_value FROM wp_usermeta WHERE meta_key = 'encrypted_token' AND user_id = [user id]`
3. **Expected:** Encrypted token exists (new feature)
4. **Expected:** Value is base64-encoded encrypted data

---

### Phase 7: Token Cleanup Testing

**Test 7.1: Verify Token Deletion on Logout**
1. Log in via OAuth2 (any provider)
2. Verify tokens exist in database
3. Log out
4. Check database again
5. **Expected:** `encrypted_token` deleted from user_meta
6. **Expected:** `oauth2_access_token` deleted from user_meta

**Test 7.2: Verify Multiple Login Cycles**
1. Log in, log out, log in again (repeat 3 times)
2. Check System Logs after each cycle
3. **Expected:** No orphaned tokens in database
4. **Expected:** Each login creates fresh tokens
5. **Expected:** Each logout cleans up tokens

---

### Phase 8: Multi-Server Scope Testing

**Test 8.1: Configure Different Scopes for Multiple Servers**
1. Set OAuth2 Server Count to 3
2. Configure Server 1 (Azure):
   - OAuth2 Scopes: `openid profile email User.Read Calendars.Read offline_access`
3. Configure Server 2 (Azure):
   - OAuth2 Scopes: `openid profile email User.Read Mail.Read offline_access`
4. Configure Server 3 (GitHub):
   - OAuth2 Scopes: `user:email read:user`
5. Save settings
6. Test login with each server
7. **Expected:** Each server uses its own configured scopes
8. **Expected:** All logins work correctly

---

## Success Criteria

### Must Pass ✅
- [ ] OAuth2 Scopes field visible in settings UI
- [ ] Azure login works with enhanced default scopes (empty field)
- [ ] Azure login works with custom scopes
- [ ] Token acquisition logs show detailed information
- [ ] Token includes `has_refresh: yes`
- [ ] Token includes `can_make_api_calls: yes`
- [ ] MS Graph API calls work with acquired token (User.Read minimum)
- [ ] GitHub login works with configurable scopes
- [ ] Generic OAuth2 login works with configurable scopes
- [ ] Tokens deleted on logout

### Should Pass ✅
- [ ] MS Graph Calendar API accessible with extended scopes
- [ ] MS Graph Mail API accessible with extended scopes
- [ ] MS Graph Tasks API accessible with extended scopes
- [ ] MS Graph Sites API accessible with extended scopes
- [ ] Refresh token can be used to get new access token
- [ ] Multiple OAuth2 servers can have different scopes
- [ ] Generic OAuth2 now stores encrypted tokens

### Nice to Have ✅
- [ ] Clear error messages when scopes are invalid
- [ ] Consent screen shows all requested permissions
- [ ] Settings help text is clear and actionable

---

## Known Limitations

1. **Admin Consent Scopes:** Scopes requiring admin consent (like `User.Read.All`, `Group.Read.All`) will fail unless tenant admin pre-consents
2. **Scope Validation:** Plugin does not validate scope syntax before attempting login
3. **Provider Differences:** Each OAuth2 provider has different scope naming conventions
4. **MS Graph URI:** Azure scopes are auto-prepended with MS Graph URI, but this may not work for all custom Azure endpoints

---

## Rollback Plan

If testing reveals critical issues:

1. **Restore Previous Commit:**
   ```bash
   git checkout b28492a
   ```

2. **Or Remove Scope Configuration:**
   - Delete "OAuth2 Scopes" field from settings
   - Hardcode scopes back to minimal defaults

3. **Database Cleanup (if needed):**
   ```sql
   DELETE FROM wp_options WHERE option_name LIKE '%oauth2_scope%';
   ```

---

## Next Steps After Testing

### If Tests Pass ✅
1. Update plugin documentation with scope configuration guide
2. Create user guide for extended MS Graph API permissions
3. Add examples for common scope configurations
4. Consider adding scope validation/suggestions in UI
5. Move to main branch and create release

### If Tests Fail ❌
1. Document which tests failed
2. Review error logs and console output
3. Fix identified issues
4. Re-test affected scenarios
5. Update this plan with lessons learned

---

## Testing Checklist

Copy this checklist for manual testing:

```
PHASE 1: Settings UI
□ OAuth2 Scopes field exists
□ Help text is clear and accurate
□ Field saves correctly

PHASE 2: Azure Default Scopes
□ Login works with empty scope field
□ Token acquisition logs show details
□ Token storage verified in database
□ Consent screen shows extended permissions

PHASE 3: Azure Custom Scopes
□ Custom scopes override defaults
□ Minimal scopes work correctly
□ Invalid scopes handled gracefully

PHASE 4: MS Graph API Access
□ User profile API works
□ Calendar API works
□ Mail API works
□ Tasks API works
□ Sites API works
□ Refresh token works

PHASE 5: GitHub Scopes
□ Default scope works
□ Custom scopes work

PHASE 6: Generic OAuth2 Scopes
□ Default scopes work
□ Custom scopes work
□ Encrypted token storage works

PHASE 7: Token Cleanup
□ Tokens deleted on logout
□ Multiple login cycles work

PHASE 8: Multi-Server
□ Different scopes per server work
```

---

## Support & Troubleshooting

### Common Issues

**Issue:** Login fails after adding custom scopes
**Solution:** Verify scopes are valid for your OAuth2 provider. Check Azure AD app registration has required API permissions configured.

**Issue:** MS Graph API returns 403 Forbidden
**Solution:** Token may not have required scope. Check token scopes by decoding access token at https://jwt.ms

**Issue:** No refresh token received
**Solution:** Ensure `offline_access` scope is included in configuration.

**Issue:** Generic OAuth2 doesn't respect scopes
**Solution:** Some providers require scopes in specific format or via different parameters. Check provider documentation.

---

## Documentation References

- [Microsoft Graph API Permissions](https://docs.microsoft.com/en-us/graph/permissions-reference)
- [GitHub OAuth Scopes](https://docs.github.com/en/developers/apps/building-oauth-apps/scopes-for-oauth-apps)
- [OAuth 2.0 Scopes](https://oauth.net/2/scope/)
- [Azure AD v2.0 Tokens](https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow)

---

**Last Updated:** 2026-01-20
**Version:** 1.0
**Status:** Ready for Testing
