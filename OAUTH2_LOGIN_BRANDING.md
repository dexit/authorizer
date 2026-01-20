# OAuth2 Login Page Branding & Customization

## Overview

The enhanced `login_enqueue_scripts_and_styles()` function provides comprehensive OAuth2 provider-specific branding and customization capabilities for the WordPress login page.

**Version:** 1.0
**Added:** 2026-01-20
**File:** `src/authorizer/class-login-form.php`

---

## Features

### 1. **OAuth2 Provider Detection**
- Automatically detects configured OAuth2 providers (Azure, GitHub, Generic)
- Supports multiple OAuth2 servers (1-20)
- Passes provider configuration to JavaScript

### 2. **Provider-Specific Branding**
- Default labels: "Sign in with Microsoft", "Sign in with GitHub", etc.
- Provider icons (Microsoft, GitHub logos as SVG data URIs)
- Provider brand colors (Microsoft blue #0078d4, GitHub dark #24292e)
- Custom button CSS classes for styling

### 3. **Dynamic Button Configuration**
- Button labels (customizable via settings or filter)
- OAuth2 scopes passed to JavaScript
- Auto-login support
- Multi-server button rendering

### 4. **Extensibility via Filters**
- `authorizer_oauth2_login_button_config` - Customize button configuration
- `authorizer_oauth2_login_branding_css` - Add custom CSS per provider
- `authorizer_oauth2_login_branding_js` - Add custom JavaScript per provider
- `authorizer_show_oauth2_scopes_on_login` - Show scopes on login page

---

## JavaScript Configuration Object

The function passes an `authorizerOAuth2Config` object to JavaScript with the following structure:

```javascript
window.authorizerOAuth2Config = {
    servers: [
        {
            server_id: 1,
            provider: 'azure',
            label: 'Sign in with Microsoft',
            scope: 'openid profile email User.Read Calendars.Read Mail.Read...',
            button_class: 'button button-primary button-hero oauth2-login-button oauth2-provider-azure',
            icon: 'data:image/svg+xml;base64,...',  // Microsoft logo SVG
            color: '#0078d4'                         // Microsoft blue
        },
        {
            server_id: 2,
            provider: 'github',
            label: 'Sign in with GitHub',
            scope: 'user:email read:user',
            button_class: 'button button-primary button-hero oauth2-login-button oauth2-provider-github',
            icon: 'data:image/svg+xml;base64,...',  // GitHub logo SVG
            color: '#24292e'                         // GitHub dark
        }
    ],
    showScopes: false,                              // Show OAuth2 scopes below buttons
    scopeLabel: 'Permissions:',                     // Label for scope display
    autoLoginEnabled: false,                        // Auto-redirect to OAuth2 provider
    loginUrl: 'https://example.com/wp-login.php'   // WordPress login URL
};
```

---

## Filter: `authorizer_oauth2_login_button_config`

Customize OAuth2 button configuration for each server.

### Parameters

- `$server_config` (array) - OAuth2 server configuration
- `$oauth2_num_server` (int) - OAuth2 server number (1-20)
- `$auth_settings` (array) - All Authorizer settings

### Server Config Keys

| Key | Type | Description |
|-----|------|-------------|
| `server_id` | int | OAuth2 server number (1-20) |
| `provider` | string | Provider type: 'azure', 'github', 'generic' |
| `label` | string | Button text label |
| `scope` | string | OAuth2 scopes (space-separated) |
| `button_class` | string | CSS classes for button |
| `icon` | string | SVG icon as data URI (Azure, GitHub only) |
| `color` | string | Brand color hex code (Azure, GitHub only) |

### Example Usage

```php
/**
 * Customize OAuth2 button configuration.
 */
function my_oauth2_button_config( $server_config, $oauth2_num_server, $auth_settings ) {
    // Change Azure button label for server 1
    if ( 1 === $oauth2_num_server && 'azure' === $server_config['provider'] ) {
        $server_config['label'] = 'Sign in with Microsoft 365';
        $server_config['button_class'] .= ' custom-azure-button';
    }

    // Add custom data attributes
    if ( 'github' === $server_config['provider'] ) {
        $server_config['data_attrs'] = array(
            'tracking' => 'github-login',
            'analytics-event' => 'oauth2_login_github',
        );
    }

    // Add custom icon for generic OAuth2
    if ( 'generic' === $server_config['provider'] ) {
        $server_config['icon'] = 'https://example.com/custom-oauth-icon.svg';
        $server_config['color'] = '#ff6600';
    }

    return $server_config;
}
add_filter( 'authorizer_oauth2_login_button_config', 'my_oauth2_button_config', 10, 3 );
```

---

## Filter: `authorizer_oauth2_login_branding_css`

Provide custom CSS for OAuth2 provider buttons.

### Parameters

- `$css_url` (string) - CSS URL (empty by default)
- `$provider` (string) - OAuth2 provider: 'azure', 'github', 'generic'
- `$server_id` (int) - OAuth2 server number (1-20)

### Example Usage

```php
/**
 * Add custom CSS for Azure OAuth2 buttons.
 */
function my_oauth2_button_css( $css_url, $provider, $server_id ) {
    if ( 'azure' === $provider ) {
        return 'https://example.edu/css/azure-button.css';
    }

    if ( 'github' === $provider ) {
        return 'https://example.edu/css/github-button.css';
    }

    return $css_url;
}
add_filter( 'authorizer_oauth2_login_branding_css', 'my_oauth2_button_css', 10, 3 );
```

**Custom CSS Example (`azure-button.css`):**
```css
.oauth2-provider-azure {
    background: linear-gradient(135deg, #0078d4 0%, #005a9e 100%);
    border: 2px solid #005a9e;
    box-shadow: 0 4px 6px rgba(0, 120, 212, 0.3);
    transition: all 0.3s ease;
}

.oauth2-provider-azure:hover {
    background: linear-gradient(135deg, #005a9e 0%, #004578 100%);
    box-shadow: 0 6px 12px rgba(0, 120, 212, 0.5);
    transform: translateY(-2px);
}

.oauth2-provider-azure::before {
    content: '';
    display: inline-block;
    width: 20px;
    height: 20px;
    margin-right: 10px;
    background-image: url('data:image/svg+xml;base64,...');
    background-size: contain;
    vertical-align: middle;
}
```

---

## Filter: `authorizer_oauth2_login_branding_js`

Provide custom JavaScript for OAuth2 provider buttons.

### Parameters

- `$js_url` (string) - JavaScript URL (empty by default)
- `$provider` (string) - OAuth2 provider: 'azure', 'github', 'generic'
- `$server_id` (int) - OAuth2 server number (1-20)

### Example Usage

```php
/**
 * Add custom JavaScript for OAuth2 buttons.
 */
function my_oauth2_button_js( $js_url, $provider, $server_id ) {
    if ( 'azure' === $provider ) {
        return 'https://example.edu/js/azure-button.js';
    }

    return $js_url;
}
add_filter( 'authorizer_oauth2_login_branding_js', 'my_oauth2_button_js', 10, 3 );
```

**Custom JavaScript Example (`azure-button.js`):**
```javascript
(function($) {
    'use strict';

    $(document).ready(function() {
        // Add tracking to Azure login buttons
        $('.oauth2-provider-azure').on('click', function(e) {
            // Send analytics event
            if (typeof gtag !== 'undefined') {
                gtag('event', 'login_attempt', {
                    'event_category': 'authentication',
                    'event_label': 'azure_oauth2',
                    'provider': 'microsoft'
                });
            }

            // Add loading state
            $(this).addClass('loading').prop('disabled', true);
            $(this).html('<span class="spinner"></span> Signing in...');
        });

        // Add tooltip showing scopes
        if (typeof authorizerOAuth2Config !== 'undefined') {
            authorizerOAuth2Config.servers.forEach(function(server) {
                if (server.provider === 'azure' && server.scope) {
                    var $button = $('.oauth2-login-button[data-server-id="' + server.server_id + '"]');
                    $button.attr('title', 'Permissions: ' + server.scope);
                }
            });
        }
    });
})(jQuery);
```

---

## Filter: `authorizer_show_oauth2_scopes_on_login`

Control whether OAuth2 scopes are displayed on the login page for transparency.

### Parameters

- `$show_scopes` (bool) - Show scopes on login page (default: false)

### Example Usage

```php
/**
 * Show OAuth2 scopes on login page for transparency.
 */
function my_show_oauth2_scopes( $show_scopes ) {
    // Show scopes only in development environment
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        return true;
    }

    // Show scopes only for admins
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }

    return false;
}
add_filter( 'authorizer_show_oauth2_scopes_on_login', 'my_show_oauth2_scopes' );
```

**Note:** When enabled, scopes are displayed below each OAuth2 login button:
```
[Sign in with Microsoft]

Permissions: openid profile email User.Read Calendars.Read Mail.Read...
```

---

## Default Provider Branding

### Microsoft Azure

**Label:** "Sign in with Microsoft"

**Icon:** Microsoft logo (4-color Windows logo)
```
🟦🟨
🟩🟧
```

**Color:** `#0078d4` (Microsoft blue)

**Button Class:** `oauth2-provider-azure`

---

### GitHub

**Label:** "Sign in with GitHub"

**Icon:** GitHub Octocat logo (white on transparent)

**Color:** `#24292e` (GitHub dark)

**Button Class:** `oauth2-provider-github`

---

### Generic OAuth2

**Label:** "Sign in with OAuth2"

**Icon:** None (can be added via filter)

**Color:** None (uses default WordPress button styling)

**Button Class:** `oauth2-provider-generic`

---

## Multi-Server Support

The function supports multiple OAuth2 servers (1-20) with independent configuration:

```php
// Example: 3 OAuth2 servers configured

Server 1: Azure (Microsoft 365)
- Label: "Sign in with Microsoft 365"
- Scopes: openid profile email User.Read Calendars.Read...

Server 2: Azure (Azure Government)
- Label: "Sign in with Azure Government"
- Scopes: openid profile email User.Read...

Server 3: GitHub
- Label: "Sign in with GitHub"
- Scopes: user:email read:user
```

Each server gets unique:
- CSS class: `oauth2-provider-{provider}-{server_id}`
- Data attribute: `data-server-id="{server_id}"`
- Configuration object in JavaScript

---

## JavaScript Integration Example

**Use Case:** Render custom OAuth2 buttons using the configuration

```javascript
(function($) {
    'use strict';

    $(document).ready(function() {
        // Check if OAuth2 config exists
        if (typeof authorizerOAuth2Config === 'undefined') {
            return;
        }

        var config = authorizerOAuth2Config;

        // Render OAuth2 buttons
        config.servers.forEach(function(server) {
            var $button = $('<a></a>')
                .addClass(server.button_class)
                .attr('href', config.loginUrl + '?external=oauth2&id=' + server.server_id)
                .attr('data-server-id', server.server_id)
                .attr('data-provider', server.provider);

            // Add icon if available
            if (server.icon) {
                $button.prepend($('<img>').attr('src', server.icon).css({
                    'width': '20px',
                    'height': '20px',
                    'margin-right': '8px',
                    'vertical-align': 'middle'
                }));
            }

            // Add label
            $button.append($('<span>').text(server.label));

            // Apply brand color
            if (server.color) {
                $button.css('background-color', server.color);
            }

            // Append to login form
            $('#loginform').prepend($button);

            // Add scope display if enabled
            if (config.showScopes && server.scope) {
                var $scopeInfo = $('<p></p>')
                    .addClass('oauth2-scope-info')
                    .css({
                        'font-size': '12px',
                        'color': '#666',
                        'margin-top': '5px',
                        'margin-bottom': '15px'
                    })
                    .html('<strong>' + config.scopeLabel + '</strong> ' + server.scope);

                $button.after($scopeInfo);
            }
        });

        // Handle auto-login if enabled
        if (config.autoLoginEnabled && config.servers.length > 0) {
            var autoLoginServer = config.servers[0]; // Use first server
            window.location.href = config.loginUrl + '?external=oauth2&id=' + autoLoginServer.server_id;
        }
    });
})(jQuery);
```

---

## CSS Styling Examples

### Modern Gradient Buttons

```css
/* Azure Button */
.oauth2-provider-azure {
    background: linear-gradient(135deg, #0078d4 0%, #005a9e 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 4px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.oauth2-provider-azure:hover {
    background: linear-gradient(135deg, #005a9e 0%, #004578 100%);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    transform: translateY(-2px);
}

/* GitHub Button */
.oauth2-provider-github {
    background: #24292e;
    color: white;
    border: 1px solid #1b1f23;
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: background 0.2s ease;
}

.oauth2-provider-github:hover {
    background: #1b1f23;
    color: white;
}
```

### Flat Design Buttons

```css
.oauth2-login-button {
    display: block;
    width: 100%;
    max-width: 320px;
    margin: 10px auto;
    padding: 14px 20px;
    text-align: center;
    border-radius: 3px;
    font-size: 16px;
    font-weight: 500;
    text-decoration: none;
    transition: opacity 0.2s;
}

.oauth2-login-button:hover {
    opacity: 0.85;
}

.oauth2-provider-azure {
    background: #0078d4;
    color: white;
    border: none;
}

.oauth2-provider-github {
    background: #333;
    color: white;
    border: none;
}

.oauth2-provider-generic {
    background: white;
    color: #333;
    border: 2px solid #ddd;
}
```

---

## Testing the Feature

### 1. Basic Test

1. Enable OAuth2 in Authorizer settings
2. Configure Azure OAuth2 provider
3. Visit wp-login.php
4. Open browser console
5. Check for `authorizerOAuth2Config` object
6. Verify server configuration is present

```javascript
// In browser console
console.log(authorizerOAuth2Config);

// Expected output:
// {
//   servers: [{
//     server_id: 1,
//     provider: 'azure',
//     label: 'Sign in with Microsoft',
//     scope: '...',
//     ...
//   }],
//   showScopes: false,
//   ...
// }
```

### 2. Multi-Server Test

1. Set OAuth2 Server Count to 3
2. Configure servers:
   - Server 1: Azure
   - Server 2: GitHub
   - Server 3: Generic
3. Visit wp-login.php
4. Verify `authorizerOAuth2Config.servers` has 3 entries

### 3. Custom CSS Test

1. Add filter for custom CSS:
   ```php
   add_filter( 'authorizer_oauth2_login_branding_css', function( $css_url, $provider ) {
       if ( 'azure' === $provider ) {
           return 'https://example.com/custom-azure.css';
       }
       return $css_url;
   }, 10, 2 );
   ```

2. Visit wp-login.php
3. Check network tab in browser dev tools
4. Verify custom CSS file is loaded

### 4. Scope Display Test

1. Add filter to show scopes:
   ```php
   add_filter( 'authorizer_show_oauth2_scopes_on_login', '__return_true' );
   ```

2. Visit wp-login.php
3. Check `authorizerOAuth2Config.showScopes === true`
4. Implement JavaScript to display scopes below buttons

---

## Backward Compatibility

The enhancement is **100% backward compatible**:

- Does not affect existing login page functionality
- Only activates when OAuth2 is enabled
- Existing OAuth2 logins continue to work unchanged
- No database changes required
- No breaking changes to filters or hooks

---

## Performance Considerations

**Optimizations:**
- Configuration built only when OAuth2 is enabled
- Empty servers skipped (no provider configured)
- CSS/JS only enqueued when custom branding provided via filters
- Minimal JavaScript payload (~2-3KB for config object)

**Caching:**
- Configuration data cached in `wp_localize_script()` output
- Browser caches custom CSS/JS files
- No additional database queries

---

## Troubleshooting

### Issue: OAuth2 config object not available in JavaScript

**Cause:** OAuth2 not enabled or no servers configured

**Solution:**
1. Check Authorizer → External → OAuth2 is enabled
2. Verify at least one OAuth2 server is configured
3. Check browser console for JavaScript errors

---

### Issue: Custom CSS not loading

**Cause:** Filter returning invalid URL or URL not accessible

**Solution:**
1. Verify filter returns valid URL
2. Check URL is publicly accessible
3. Check browser network tab for 404 errors
4. Verify HTTPS if login page uses HTTPS

---

### Issue: Provider icons not displaying

**Cause:** SVG data URI invalid or browser blocking inline SVG

**Solution:**
1. Test icon SVG separately
2. Check Content Security Policy (CSP) headers
3. Use external image URL instead of data URI
4. Verify icon URL is accessible

---

## Future Enhancements

**Planned Features:**
1. Built-in CSS themes (modern, flat, minimal, corporate)
2. Icon library for more OAuth2 providers
3. Button position configuration (top, bottom, sidebar)
4. Button size options (small, medium, large, hero)
5. Color scheme customization UI in settings
6. Live preview in Authorizer settings
7. Support for OAuth2 provider brand guidelines
8. Accessibility improvements (ARIA labels, keyboard navigation)

---

## Related Documentation

- [OAuth2 Scope Configuration](OAUTH2_SCOPE_IMPLEMENTATION_PLAN.md)
- [OAuth2 Testing Plan](OAUTH2_SCOPE_TESTING_PLAN.md)
- [Regression Testing](REGRESSION_TEST_VERIFICATION.md)

---

**Document Version:** 1.0
**Last Updated:** 2026-01-20
**Author:** Claude Code Assistant
