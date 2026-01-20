# Login Branding - Phase 1 Implementation

## Scope: Essential Login Page Customization

**Phase:** 1 (Essential Features)
**Status:** 🚧 In Progress
**Estimated Time:** 2-3 hours
**Files Modified:** 3 files

---

## Phase 1 Features (6 Settings)

### Logo Customization
1. **Logo URL** (text) - Direct image URL
2. **Logo Link** (text) - Where logo links to (default: home_url())
3. **Logo Alt Text** (text) - Accessibility text

### Color Customization
4. **Primary Color** (color picker) - Buttons, links

### Background
5. **Background Color** (color picker) - Page background

### Advanced
6. **Custom CSS** (textarea) - Full CSS control

**Total Settings:** 6 fields

---

## Why Phase 1 First?

### Benefits
- ✅ Quick implementation (2-3 hours vs 8-13 hours)
- ✅ Immediate value (most-requested features)
- ✅ Test infrastructure before full implementation
- ✅ Get user feedback before investing in full UI
- ✅ Backward compatible
- ✅ Easy to extend to Phase 2

### Deferred to Phase 2
- Logo uploader (media library integration)
- Multiple color pickers (secondary, text, input, etc.)
- Background images/gradients
- Meta boxes UI
- Form positioning
- Shadow controls
- Mobile responsiveness toggles
- Live preview

---

## Implementation Details

### Settings Fields

#### 1. Logo URL
```php
/**
 * Logo image URL for login page
 *
 * @type   string
 * @format URL
 * @example https://example.com/logo.png
 */
login_logo_url
```

#### 2. Logo Link URL
```php
/**
 * URL the logo links to
 *
 * @type   string
 * @format URL
 * @default home_url()
 * @example https://example.com
 */
login_logo_link
```

#### 3. Logo Alt Text
```php
/**
 * Alt text for logo (accessibility)
 *
 * @type   string
 * @default get_bloginfo('name')
 * @example My Company
 */
login_logo_alt
```

#### 4. Primary Color
```php
/**
 * Primary color for buttons and links
 *
 * @type   string
 * @format hex color
 * @default #0073aa
 * @example #ff6600
 */
login_color_primary
```

#### 5. Background Color
```php
/**
 * Background color for login page
 *
 * @type   string
 * @format hex color
 * @default #f0f0f1
 * @example #ffffff
 */
login_bg_color
```

#### 6. Custom CSS
```php
/**
 * Custom CSS for login page
 *
 * @type   string
 * @format CSS code
 * @example body.login { font-family: Arial; }
 */
login_custom_css
```

---

## File Changes

### File 1: `src/authorizer/options/class-advanced.php`

**Add 6 print methods:**

```php
public function print_text_login_logo_url( $args = '' )
public function print_text_login_logo_link( $args = '' )
public function print_text_login_logo_alt( $args = '' )
public function print_color_login_color_primary( $args = '' )
public function print_color_login_bg_color( $args = '' )
public function print_textarea_login_custom_css( $args = '' )
```

**Estimated Lines:** +150

---

### File 2: `src/authorizer/class-admin-page.php`

**Add settings section and field registrations:**

```php
// Add section
add_settings_section(
    'auth_settings_advanced_login_branding',
    __( 'Login Page Branding', 'authorizer' ),
    array( Advanced::get_instance(), 'print_section_info_login_branding' ),
    'authorizer'
);

// Add 6 fields
add_settings_field( 'auth_settings_login_logo_url', ... );
add_settings_field( 'auth_settings_login_logo_link', ... );
add_settings_field( 'auth_settings_login_logo_alt', ... );
add_settings_field( 'auth_settings_login_color_primary', ... );
add_settings_field( 'auth_settings_login_bg_color', ... );
add_settings_field( 'auth_settings_login_custom_css', ... );
```

**Estimated Lines:** +50

---

### File 3: `src/authorizer/class-options.php`

**Add sanitization for 6 fields:**

```php
// Sanitize login logo URL
if ( array_key_exists( 'login_logo_url', $auth_settings ) ) {
    $auth_settings['login_logo_url'] = esc_url_raw( $auth_settings['login_logo_url'] );
}

// Sanitize login logo link
if ( array_key_exists( 'login_logo_link', $auth_settings ) ) {
    $auth_settings['login_logo_link'] = esc_url_raw( $auth_settings['login_logo_link'] );
}

// Sanitize logo alt text
if ( array_key_exists( 'login_logo_alt', $auth_settings ) ) {
    $auth_settings['login_logo_alt'] = sanitize_text_field( $auth_settings['login_logo_alt'] );
}

// Sanitize primary color
if ( array_key_exists( 'login_color_primary', $auth_settings ) ) {
    $color = sanitize_text_field( $auth_settings['login_color_primary'] );
    // Validate hex color format
    if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
        $auth_settings['login_color_primary'] = $color;
    } else {
        $auth_settings['login_color_primary'] = '#0073aa'; // Default
    }
}

// Sanitize background color
if ( array_key_exists( 'login_bg_color', $auth_settings ) ) {
    $color = sanitize_text_field( $auth_settings['login_bg_color'] );
    if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
        $auth_settings['login_bg_color'] = $color;
    } else {
        $auth_settings['login_bg_color'] = '#f0f0f1'; // Default
    }
}

// Sanitize custom CSS
if ( array_key_exists( 'login_custom_css', $auth_settings ) ) {
    // Strip <script> tags and JavaScript
    $css = $auth_settings['login_custom_css'];
    $css = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $css );
    $css = wp_strip_all_tags( $css, true );
    $auth_settings['login_custom_css'] = $css;
}
```

**Estimated Lines:** +45

---

### File 4: `src/authorizer/class-login-form.php`

**Extend `login_enqueue_scripts_and_styles()` to generate CSS:**

```php
// After existing OAuth2 branding code, add login branding CSS

$login_logo_url     = ! empty( $auth_settings['login_logo_url'] ) ? $auth_settings['login_logo_url'] : '';
$login_logo_link    = ! empty( $auth_settings['login_logo_link'] ) ? $auth_settings['login_logo_link'] : home_url();
$login_logo_alt     = ! empty( $auth_settings['login_logo_alt'] ) ? $auth_settings['login_logo_alt'] : get_bloginfo( 'name' );
$login_color_primary = ! empty( $auth_settings['login_color_primary'] ) ? $auth_settings['login_color_primary'] : '#0073aa';
$login_bg_color     = ! empty( $auth_settings['login_bg_color'] ) ? $auth_settings['login_bg_color'] : '#f0f0f1';
$login_custom_css   = ! empty( $auth_settings['login_custom_css'] ) ? $auth_settings['login_custom_css'] : '';

// Generate dynamic CSS
$dynamic_css = '';

if ( ! empty( $login_logo_url ) ) {
    $dynamic_css .= "
        #login h1 a {
            background-image: url('" . esc_url( $login_logo_url ) . "') !important;
            background-size: contain;
            width: 320px;
            height: 80px;
        }
    ";
}

if ( '#0073aa' !== $login_color_primary ) {
    $dynamic_css .= "
        .wp-core-ui .button-primary {
            background: " . esc_attr( $login_color_primary ) . " !important;
            border-color: " . esc_attr( $login_color_primary ) . " !important;
        }
        .wp-core-ui .button-primary:hover {
            background: " . esc_attr( $login_color_primary ) . " !important;
            opacity: 0.9;
        }
        #login a {
            color: " . esc_attr( $login_color_primary ) . " !important;
        }
    ";
}

if ( '#f0f0f1' !== $login_bg_color ) {
    $dynamic_css .= "
        body.login {
            background-color: " . esc_attr( $login_bg_color ) . " !important;
        }
    ";
}

// Add custom CSS (already sanitized)
if ( ! empty( $login_custom_css ) ) {
    $dynamic_css .= "\n" . $login_custom_css;
}

// Enqueue dynamic CSS
if ( ! empty( $dynamic_css ) ) {
    wp_add_inline_style( 'authorizer-login-css', $dynamic_css );
}

// Add login_headerurl and login_headertext filters
add_filter( 'login_headerurl', function() use ( $login_logo_link ) {
    return $login_logo_link;
} );

add_filter( 'login_headertext', function() use ( $login_logo_alt ) {
    return $login_logo_alt;
} );
```

**Estimated Lines:** +70

---

## Total Impact

| File | Lines Added |
|------|-------------|
| class-advanced.php | +150 |
| class-admin-page.php | +50 |
| class-options.php | +45 |
| class-login-form.php | +70 |
| **Total** | **+315 lines** |

---

## Generated CSS Example

```css
/* Login Page Branding - Phase 1 */

/* Custom Logo */
#login h1 a {
    background-image: url('https://example.com/logo.png') !important;
    background-size: contain;
    width: 320px;
    height: 80px;
}

/* Primary Color */
.wp-core-ui .button-primary {
    background: #ff6600 !important;
    border-color: #ff6600 !important;
}

.wp-core-ui .button-primary:hover {
    background: #ff6600 !important;
    opacity: 0.9;
}

#login a {
    color: #ff6600 !important;
}

/* Background Color */
body.login {
    background-color: #ffffff !important;
}

/* Custom CSS */
body.login {
    font-family: 'Arial', sans-serif;
}

#loginform {
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}
```

---

## Settings UI Preview

```
Advanced Tab → Login Page Branding Section

┌─────────────────────────────────────────────────────────┐
│ Login Page Branding                                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Logo URL:                                              │
│ [https://example.com/logo.png___________________]      │
│ Enter the full URL to your logo image.                │
│                                                         │
│ Logo Link URL:                                         │
│ [https://example.com__________________________]        │
│ Where should the logo link to? (default: home page)   │
│                                                         │
│ Logo Alt Text:                                         │
│ [My Company Name____________________________]          │
│ Accessibility text for your logo                      │
│                                                         │
│ Primary Color (Buttons & Links):                      │
│ [🎨 #0073aa]                                           │
│                                                         │
│ Background Color:                                       │
│ [🎨 #f0f0f1]                                           │
│                                                         │
│ Custom CSS:                                            │
│ ┌─────────────────────────────────────────────────┐   │
│ │ /* Add your custom CSS here */                  │   │
│ │ body.login {                                     │   │
│ │     font-family: 'Arial', sans-serif;            │   │
│ │ }                                                 │   │
│ └─────────────────────────────────────────────────┘   │
│                                                         │
│ [Save Changes]                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Testing Checklist

### Functional Tests
- [ ] Logo URL field saves correctly
- [ ] Logo displays on login page
- [ ] Logo links to specified URL
- [ ] Logo alt text shows correctly
- [ ] Primary color applies to buttons
- [ ] Primary color applies to links
- [ ] Background color applies to page
- [ ] Custom CSS applies correctly
- [ ] Settings persist after save

### Visual Tests
- [ ] Login page looks good with custom branding
- [ ] Colors have sufficient contrast (accessibility)
- [ ] Logo scales properly
- [ ] Layout doesn't break
- [ ] Mobile responsive (default WP behavior)

### Security Tests
- [ ] URLs are sanitized (no XSS)
- [ ] Colors are validated (hex format)
- [ ] Custom CSS is safe (no <script> tags)
- [ ] Settings properly escaped on output

---

## Phase 2 Enhancements (Future)

After Phase 1 is tested and working, Phase 2 will add:

1. **Logo Uploader** - WordPress media library integration
2. **Multiple Colors** - Secondary, text, input, link colors
3. **Background Image** - Upload or URL with position/size controls
4. **Gradient Backgrounds** - Two-color gradients
5. **Meta Boxes** - Proper WordPress metabox UI
6. **Form Controls** - Width, shadow, positioning
7. **Hide/Show Elements** - Remember me, lost password, etc.
8. **Live Preview** - See changes before saving
9. **Preset Templates** - Professional, Modern, Minimal, etc.
10. **Mobile Settings** - Responsive customization

**Phase 2 Estimate:** Additional 6-10 hours

---

## Success Criteria

Phase 1 is successful when:

- ✅ All 6 settings save and load correctly
- ✅ Logo, colors, and CSS apply to login page
- ✅ No errors in browser console
- ✅ No PHP errors or warnings
- ✅ Accessibility maintained (color contrast, alt text)
- ✅ Works on latest WordPress version
- ✅ Backward compatible (empty settings = default appearance)

---

## Next Steps

1. ✅ Document Phase 1 scope (this document)
2. 🚧 Implement print methods in class-advanced.php
3. 🚧 Register settings in class-admin-page.php
4. 🚧 Add sanitization in class-options.php
5. 🚧 Generate CSS in class-login-form.php
6. 🚧 Test functionality
7. 🚧 Commit and push
8. 📋 Get user feedback
9. 📋 Plan Phase 2 based on feedback

---

**Document Version:** 1.0
**Created:** 2026-01-20
**Status:** 🚧 Implementation in Progress
