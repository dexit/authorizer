# OAuth2 Quick Testing Verification Checklist

## Ready to Test: OAuth2 Configurable Scopes

**Branch:** `claude/office365-complete-J5669`
**Status:** ✅ Code Complete - Ready for Manual Testing

---

## Pre-Testing Setup

### Azure AD App Registration (Required)

1. **Go to Azure Portal:** https://portal.azure.com
2. **Navigate to:** Azure Active Directory → App Registrations
3. **Create/Edit App:**
   - Redirect URI: `https://your-site.com/wp-login.php`
   - Platform: Web

4. **API Permissions → Add permissions → Microsoft Graph → Delegated:**
   - ✅ openid
   - ✅ profile
   - ✅ email
   - ✅ User.Read
   - ✅ Calendars.Read
   - ✅ Mail.Read
   - ✅ Tasks.Read
   - ✅ Sites.Read.All
   - ✅ offline_access

5. **Save Client ID and Client Secret**

### GitHub OAuth App (Optional)

1. **Go to:** https://github.com/settings/developers
2. **New OAuth App:**
   - Authorization callback URL: `https://your-site.com/wp-login.php?external=oauth2`
3. **Save Client ID and Client Secret**

---

## Quick Testing Checklist (30 minutes)

### Phase 1: Settings UI Verification (5 min)

```
□ Go to: WordPress Admin → Authorizer → External → OAuth2
□ Verify "OAuth2 Scopes" field exists
□ Position: Between "Tenant ID" and "Authorization URL"
□ Type: Textarea (multi-line)
□ Help text shows examples for Azure, GitHub, Generic
```

**Expected:** Field visible with helpful examples

---

### Phase 2: Azure Default Scopes Test (10 min)

#### Configure Azure OAuth2

```
□ Provider: Azure
□ Client ID: [paste from Azure portal]
□ Client Secret: [paste from Azure portal]
□ Tenant ID: common (or your specific tenant)
□ OAuth2 Scopes: LEAVE EMPTY (test defaults)
□ Click "Save Changes"
```

#### Test Login

```
□ Open incognito/private browser window
□ Go to: https://your-site.com/wp-login.php
□ Click "Login with Azure" or similar button
□ Login with Microsoft account
□ VERIFY: Consent screen shows extended permissions:
   - Read your calendars
   - Read your mail
   - Read your tasks and task lists
   - Read items in all site collections
   - Maintain access to data
□ Grant consent
□ VERIFY: Successfully logged into WordPress
```

#### Verify Token Storage

**Database Query:**
```sql
SELECT meta_key, LENGTH(meta_value) as value_length
FROM wp_usermeta
WHERE user_id = [your_user_id]
AND (meta_key LIKE '%oauth2%' OR meta_key = 'encrypted_token');
```

**Expected Results:**
```
meta_key                | value_length
------------------------+-------------
oauth2_access_token     | ~200-300
oauth2_refresh_token    | ~200-300
oauth2_token_expires    | ~10
encrypted_token         | ~500+
```

**All 4 rows present = ✅ PASS**

#### Check System Logs

```
□ Go to: Authorizer → Logs (if System Logs tab exists)
□ Or check: WordPress Admin → Tools → Site Health → Info → Authorizer
□ Find most recent "token_acquired" event
```

**Expected Log Entry:**
```
Event: token_acquired
Status: success
Message: OAuth2 access token acquired successfully for MS Graph API requests
Details:
  - provider: azure
  - has_refresh: yes
  - expires_in: 3599 seconds (or similar)
  - token_type: Bearer
  - can_make_api_calls: yes
```

**All fields show expected values = ✅ PASS**

---

### Phase 3: Custom Scopes Test (5 min)

#### Change Scopes

```
□ Go to: Authorizer → External → OAuth2
□ OAuth2 Scopes: openid profile email User.Read offline_access
  (minimal scopes - no Calendar, Mail, Tasks, Sites)
□ Click "Save Changes"
```

#### Test Login Again

```
□ Log out of WordPress
□ Clear browser cookies/cache
□ Go to wp-login.php
□ Login with Azure again
□ VERIFY: Consent screen shows ONLY:
   - Sign you in and read your profile
   - Maintain access to data
  (NO calendar, mail, tasks, sites)
□ Grant consent
□ VERIFY: Login successful
```

**Minimal scopes work = ✅ PASS**

---

### Phase 4: GitHub OAuth2 Test (5 min)

#### Configure GitHub OAuth2

```
□ Go to: Authorizer → External → OAuth2
□ OAuth2 Server Count: 2 (to test multi-server)
□ Server 2 Configuration:
   - Provider: GitHub
   - Client ID: [from GitHub]
   - Client Secret: [from GitHub]
   - OAuth2 Scopes: user:email read:user
□ Click "Save Changes"
```

#### Test GitHub Login

```
□ Go to wp-login.php
□ Click "Login with GitHub" button
□ Authorize on GitHub
□ VERIFY: Login successful
□ VERIFY: Database has tokens for GitHub user
```

**GitHub login works = ✅ PASS**

---

### Phase 5: Token Cleanup Test (5 min)

#### Verify Token Deletion on Logout

```
□ While logged in via OAuth2, note your user ID
□ Check database - tokens should exist
□ Log out of WordPress
□ Check database again
```

**Database Query:**
```sql
SELECT meta_key
FROM wp_usermeta
WHERE user_id = [your_user_id]
AND meta_key = 'encrypted_token';
```

**Expected:** 0 rows (encrypted_token deleted)

**Token cleanup works = ✅ PASS**

---

## Quick Pass/Fail Summary

```
Test Phase                          | Result
------------------------------------|--------
Settings UI visible                 | □ PASS □ FAIL
Azure login with default scopes     | □ PASS □ FAIL
Token storage (4 meta keys)         | □ PASS □ FAIL
System logs show token details      | □ PASS □ FAIL
Custom scopes override defaults     | □ PASS □ FAIL
GitHub multi-server works           | □ PASS □ FAIL
Token cleanup on logout             | □ PASS □ FAIL
```

**Overall Status:** `□ ALL PASS` `□ SOME FAIL` `□ NOT TESTED`

---

## If Tests Fail

### Common Issues & Solutions

#### Issue: "Invalid scope" error from Azure

**Solution:**
1. Go to Azure Portal → App Registrations → Your App → API permissions
2. Verify all scopes are added (User.Read, Calendars.Read, etc.)
3. Click "Grant admin consent" if available
4. Try login again

---

#### Issue: No tokens in database

**Solution:**
1. Check WordPress error log: `wp-content/debug.log`
2. Enable WordPress debugging:
   ```php
   // In wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
3. Try login again and check logs for errors

---

#### Issue: "Consent required" on every login

**Solution:**
1. Verify `offline_access` scope is included
2. Check if refresh_token is being stored in database
3. May need to re-consent after scope changes

---

## Full Testing

For comprehensive testing, see:
- **Full Test Plan:** `OAUTH2_SCOPE_TESTING_PLAN.md`
- **Code Verification:** `REGRESSION_TEST_VERIFICATION.md`

---

## Report Results

After testing, please report:

```
✅ PASS - All tests passed
□ Token storage works for all providers
□ Extended scopes work
□ Custom scopes override defaults
□ Multi-server configuration works
□ No errors in logs

❌ FAIL - Some tests failed
□ Which tests failed: ___________________
□ Error messages: ______________________
□ Screenshots: _________________________
```

---

**Quick Test Time:** ~30 minutes
**Full Test Time:** ~2 hours (see OAUTH2_SCOPE_TESTING_PLAN.md)

**Current Status:** Awaiting test results before proceeding with login branding implementation
