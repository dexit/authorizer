# Custom Login Branding Settings - Implementation Plan

## Overview

Add comprehensive custom login branding settings with proper WordPress admin UI (meta boxes) to customize the WordPress login page appearance including logo, colors, background, and CSS.

**Feature Request:** Settings UI with meta boxes for login page customization
**Parent Feature:** OAuth2 Provider-Specific Branding
**Status:** 📋 Planned
**Priority:** Medium
**Estimated Effort:** 4-6 hours

---

## Business Requirements

### User Stories

**As a WordPress administrator, I want to:**
1. Upload a custom logo for the login page
2. Set custom colors for the login form (background, buttons, links)
3. Upload or specify a background image/color
4. Add custom CSS for advanced styling
5. Preview branding changes before publishing
6. Hide/show the WordPress logo
7. Apply branding per OAuth2 provider (Azure, GitHub, Generic)

### Acceptance Criteria

- [ ] Settings appear in Advanced tab with proper meta boxes
- [ ] Logo uploader with image preview
- [ ] Color pickers for all customizable elements
- [ ] Background options (color, image, gradient)
- [ ] Custom CSS textarea with syntax highlighting
- [ ] Real-time preview of branding changes
- [ ] Mobile-responsive login page
- [ ] All settings save correctly
- [ ] Settings apply to wp-login.php
- [ ] Compatible with existing WordPress login customizers

---

## Feature Specification

### Settings Location

**Tab:** Advanced → Login Branding

**Meta Boxes:**
1. Logo Settings
2. Color Scheme
3. Background
4. Custom CSS
5. Advanced Options

---

## Settings Fields Design

### Meta Box 1: Logo Settings

```
┌─────────────────────────────────────────────────────────┐
│ Logo Settings                                          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Custom Logo:                                           │
│ ┌─────────────────┐                                    │
│ │                 │ [Upload Logo]  [Remove]           │
│ │  [Preview]      │                                    │
│ │                 │ Recommended size: 320x80px        │
│ └─────────────────┘ Max file size: 2MB                │
│                                                         │
│ Logo Link URL:                                         │
│ [________________________] (default: home_url())      │
│                                                         │
│ Logo Alt Text:                                         │
│ [________________________]                             │
│                                                         │
│ ☑ Hide WordPress Logo                                 │
│ ☐ Center Logo                                          │
│ ☐ Scale Logo to Fit Container                         │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Fields:**
- `login_logo_id` (int) - WordPress media library attachment ID
- `login_logo_url` (string) - Logo image URL (if using external URL)
- `login_logo_link` (string) - Logo link destination URL
- `login_logo_alt` (string) - Logo alt text
- `login_hide_wp_logo` (checkbox) - Hide default WordPress logo
- `login_center_logo` (checkbox) - Center logo horizontally
- `login_logo_scale` (checkbox) - Scale logo to fit container

### Meta Box 2: Color Scheme

```
┌─────────────────────────────────────────────────────────┐
│ Color Scheme                                           │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Preset Color Schemes:                                  │
│ ○ Default (WordPress Blue)                             │
│ ○ Corporate (Professional Blue/Gray)                   │
│ ○ Modern (Clean White/Blue)                            │
│ ○ Dark Mode (Dark Gray/White)                          │
│ ● Custom (Define your own colors)                      │
│                                                         │
│ ┌──────────────────────────────────────────────────┐  │
│ │ Custom Colors (when Custom is selected)          │  │
│ ├──────────────────────────────────────────────────┤  │
│ │                                                   │  │
│ │ Primary Color:       [🎨 #0073aa]                │  │
│ │ (buttons, links)                                  │  │
│ │                                                   │  │
│ │ Secondary Color:     [🎨 #0085ba]                │  │
│ │ (hover states, accents)                           │  │
│ │                                                   │  │
│ │ Text Color:          [🎨 #444444]                │  │
│ │ (form text, labels)                               │  │
│ │                                                   │  │
│ │ Link Color:          [🎨 #0073aa]                │  │
│ │ (footer links, help text)                         │  │
│ │                                                   │  │
│ │ Form Background:     [🎨 #ffffff]                │  │
│ │ (login form box background)                       │  │
│ │                                                   │  │
│ │ Input Background:    [🎨 #fbfbfb]                │  │
│ │ (input field backgrounds)                         │  │
│ │                                                   │  │
│ │ Input Border:        [🎨 #dddddd]                │  │
│ │ (input field borders)                             │  │
│ │                                                   │  │
│ └──────────────────────────────────────────────────┘  │
│                                                         │
│ [Preview Color Scheme]                                 │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Fields:**
- `login_color_scheme` (radio) - Preset or custom
- `login_color_primary` (color) - Primary color
- `login_color_secondary` (color) - Secondary color
- `login_color_text` (color) - Text color
- `login_color_link` (color) - Link color
- `login_form_bg` (color) - Form background
- `login_input_bg` (color) - Input background
- `login_input_border` (color) - Input border

### Meta Box 3: Background

```
┌─────────────────────────────────────────────────────────┐
│ Background                                              │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Background Type:                                        │
│ ○ Solid Color                                          │
│ ○ Gradient                                              │
│ ● Image                                                 │
│ ○ None (default)                                        │
│                                                         │
│ ┌──────────────────────────────────────────────────┐  │
│ │ Solid Color                                       │  │
│ ├──────────────────────────────────────────────────┤  │
│ │ Background Color: [🎨 #f1f1f1]                   │  │
│ └──────────────────────────────────────────────────┘  │
│                                                         │
│ ┌──────────────────────────────────────────────────┐  │
│ │ Gradient                                          │  │
│ ├──────────────────────────────────────────────────┤  │
│ │ Start Color:  [🎨 #0073aa]                       │  │
│ │ End Color:    [🎨 #005177]                       │  │
│ │ Direction:    [Linear ▼] [135deg]               │  │
│ └──────────────────────────────────────────────────┘  │
│                                                         │
│ ┌──────────────────────────────────────────────────┐  │
│ │ Image                                             │  │
│ ├──────────────────────────────────────────────────┤  │
│ │ ┌──────────────┐                                 │  │
│ │ │              │ [Upload Image]  [Remove]        │  │
│ │ │  [Preview]   │                                 │  │
│ │ │              │ Recommended: 1920x1080px       │  │
│ │ └──────────────┘                                 │  │
│ │                                                   │  │
│ │ Position:  [Center ▼]                            │  │
│ │ Size:      [Cover ▼]                             │  │
│ │ Repeat:    [No Repeat ▼]                         │  │
│ │ Attachment:[Fixed ▼]                             │  │
│ │                                                   │  │
│ │ ☐ Add Overlay (to improve text readability)     │  │
│ │   Overlay Color:   [🎨 #000000]                 │  │
│ │   Overlay Opacity: [50%] ─────────────           │  │
│ └──────────────────────────────────────────────────┘  │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Fields:**
- `login_bg_type` (radio) - solid, gradient, image, none
- `login_bg_color` (color) - Solid background color
- `login_bg_gradient_start` (color) - Gradient start color
- `login_bg_gradient_end` (color) - Gradient end color
- `login_bg_gradient_direction` (select) - Gradient direction
- `login_bg_image_id` (int) - Background image attachment ID
- `login_bg_image_url` (string) - Background image URL
- `login_bg_position` (select) - Background position
- `login_bg_size` (select) - Background size
- `login_bg_repeat` (select) - Background repeat
- `login_bg_attachment` (select) - Background attachment
- `login_bg_overlay` (checkbox) - Enable overlay
- `login_bg_overlay_color` (color) - Overlay color
- `login_bg_overlay_opacity` (number) - Overlay opacity (0-100)

### Meta Box 4: Custom CSS

```
┌─────────────────────────────────────────────────────────┐
│ Custom CSS                                              │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Add your own CSS to further customize the login page:  │
│                                                         │
│ ┌─────────────────────────────────────────────────┐   │
│ │ /* Your custom CSS */                            │   │
│ │ body.login {                                     │   │
│ │     font-family: 'Arial', sans-serif;            │   │
│ │ }                                                 │   │
│ │                                                   │   │
│ │ #login h1 a {                                    │   │
│ │     background-size: contain;                    │   │
│ │ }                                                 │   │
│ │                                                   │   │
│ │                                                   │   │
│ │                                                   │   │
│ │                                                   │   │
│ │                                                   │   │
│ └─────────────────────────────────────────────────┘   │
│ 15 lines                                    [Expand]   │
│                                                         │
│ ☐ Minify CSS (reduces file size)                       │
│                                                         │
│ [Validate CSS]                                          │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Fields:**
- `login_custom_css` (textarea) - Custom CSS code
- `login_minify_css` (checkbox) - Minify CSS

### Meta Box 5: Advanced Options

```
┌─────────────────────────────────────────────────────────┐
│ Advanced Options                                        │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Form Position:                                          │
│ ○ Center (default)                                      │
│ ○ Left                                                  │
│ ○ Right                                                 │
│                                                         │
│ Form Width:                                            │
│ [320] px  (default: 320px, range: 280-600px)          │
│                                                         │
│ Form Shadow:                                           │
│ ☑ Enable form shadow for depth                        │
│ Shadow Color: [🎨 #000000]                            │
│ Shadow Blur:  [10px] ──────────                       │
│                                                         │
│ Additional Options:                                    │
│ ☑ Round form corners                                   │
│ ☐ Transparent form background                         │
│ ☐ Hide "Remember Me" checkbox                         │
│ ☐ Hide "Lost your password?" link                     │
│ ☐ Hide "← Back to {site name}" link                   │
│                                                         │
│ ☑ Apply branding to password reset page               │
│ ☑ Apply branding to registration page                 │
│                                                         │
│ Mobile Responsiveness:                                 │
│ ☑ Enable responsive design                            │
│ ☑ Adjust logo size on mobile                          │
│ ☑ Simplify background on mobile                       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Fields:**
- `login_form_position` (radio) - Form position
- `login_form_width` (number) - Form width in pixels
- `login_form_shadow` (checkbox) - Enable shadow
- `login_form_shadow_color` (color) - Shadow color
- `login_form_shadow_blur` (number) - Shadow blur radius
- `login_form_rounded` (checkbox) - Round corners
- `login_form_transparent` (checkbox) - Transparent background
- `login_hide_rememberme` (checkbox) - Hide remember me checkbox
- `login_hide_lostpassword` (checkbox) - Hide lost password link
- `login_hide_backtoblog` (checkbox) - Hide back to site link
- `login_apply_to_reset` (checkbox) - Apply to password reset
- `login_apply_to_register` (checkbox) - Apply to registration
- `login_responsive` (checkbox) - Enable responsive design
- `login_responsive_logo` (checkbox) - Adjust logo on mobile
- `login_responsive_bg` (checkbox) - Simplify background on mobile

---

## Implementation Plan

### Phase 1: Settings Registration

**File:** `src/authorizer/class-admin-page.php`

**Tasks:**
1. Add meta box registrations for login branding
2. Add settings field registrations
3. Add section callbacks

**Estimated Time:** 1-2 hours

### Phase 2: UI Methods

**File:** `src/authorizer/options/class-advanced.php`

**Tasks:**
1. Create print methods for all settings fields:
   - `print_logo_uploader_login_logo()`
   - `print_color_picker_login_color_primary()`
   - `print_radio_login_bg_type()`
   - `print_image_uploader_login_bg_image()`
   - `print_textarea_login_custom_css()`
   - `print_checkbox_login_form_shadow()`
   - ... (and more)

**Estimated Time:** 2-3 hours

### Phase 3: Sanitization

**File:** `src/authorizer/class-options.php`

**Tasks:**
1. Add sanitization for all new fields
2. Validate color values (hex format)
3. Validate URLs
4. Sanitize CSS (remove dangerous code)
5. Validate number ranges

**Estimated Time:** 1 hour

### Phase 4: CSS Generation & Application

**File:** `src/authorizer/class-login-form.php`

**Tasks:**
1. Extend `login_enqueue_scripts_and_styles()`
2. Generate dynamic CSS based on settings
3. Apply logo, colors, background
4. Add custom CSS
5. Handle media queries for responsive design

**Estimated Time:** 1-2 hours

### Phase 5: Preview System (Optional)

**Files:**
- `src/authorizer/js/authorizer-admin-branding-preview.js`
- `src/authorizer/css/authorizer-admin-branding-preview.css`

**Tasks:**
1. Create live preview iframe
2. Update preview on settings change
3. Add preview modal/lightbox
4. Save preview state

**Estimated Time:** 2-3 hours (optional)

---

## Technical Specifications

### Generated CSS Structure

```css
/* Login Page Branding - Generated by Authorizer */

/* Logo */
#login h1 a {
    background-image: url('{login_logo_url}') !important;
    background-size: contain;
    width: 320px;
    height: 80px;
}

/* Colors */
body.login {
    color: {login_color_text};
}

.wp-core-ui .button-primary {
    background: {login_color_primary};
    border-color: {login_color_primary};
}

.wp-core-ui .button-primary:hover {
    background: {login_color_secondary};
    border-color: {login_color_secondary};
}

#login a {
    color: {login_color_link};
}

/* Form */
#loginform {
    background: {login_form_bg};
    box-shadow: {login_form_shadow_settings};
    border-radius: {login_form_rounded ? '4px' : '0'};
}

#loginform input[type="text"],
#loginform input[type="password"] {
    background: {login_input_bg};
    border-color: {login_input_border};
}

/* Background */
body.login {
    background-color: {login_bg_color};
    background-image: {login_bg_type === 'gradient' ? gradient : url(image)};
    background-size: {login_bg_size};
    background-position: {login_bg_position};
    background-repeat: {login_bg_repeat};
    background-attachment: {login_bg_attachment};
}

body.login::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: {login_bg_overlay_color};
    opacity: {login_bg_overlay_opacity / 100};
    z-index: -1;
}

/* Custom CSS */
{login_custom_css}
```

### WordPress Hooks Used

**Existing Hooks:**
- `login_enqueue_scripts` - Enqueue CSS/JS
- `login_headerurl` - Logo link URL
- `login_headertext` - Logo alt text
- `login_head` - Add inline CSS

**New Hooks (to be added):**
- `authorizer_login_branding_css` - Filter generated CSS
- `authorizer_login_branding_preview` - Filter preview HTML

---

## User Interface Mockup

### Settings Page Layout

```
┌───────────────────────────────────────────────────────────────┐
│ Authorizer › Settings                                         │
├───────────────────────────────────────────────────────────────┤
│                                                               │
│ [Login Access] [Public Access] [External] [Advanced]         │
│                                                ──────────      │
│                                                                │
│ ┌─────────────────────────────────────────────────────────┐  │
│ │ Login Branding                                          │  │
│ └─────────────────────────────────────────────────────────┘  │
│                                                                │
│ ┌─────────────────────────────────────────────────────────┐  │
│ │ Logo Settings                                   [▼]     │  │
│ ├─────────────────────────────────────────────────────────┤  │
│ │ ... (logo settings fields) ...                          │  │
│ └─────────────────────────────────────────────────────────┘  │
│                                                                │
│ ┌─────────────────────────────────────────────────────────┐  │
│ │ Color Scheme                                    [▼]     │  │
│ ├─────────────────────────────────────────────────────────┤  │
│ │ ... (color scheme fields) ...                           │  │
│ └─────────────────────────────────────────────────────────┘  │
│                                                                │
│ ┌─────────────────────────────────────────────────────────┐  │
│ │ Background                                      [▼]     │  │
│ ├─────────────────────────────────────────────────────────┤  │
│ │ ... (background fields) ...                             │  │
│ └─────────────────────────────────────────────────────────┘  │
│                                                                │
│ ┌─────────────────────────────────────────────────────────┐  │
│ │ Custom CSS                                      [▼]     │  │
│ ├─────────────────────────────────────────────────────────┤  │
│ │ ... (custom CSS textarea) ...                           │  │
│ └─────────────────────────────────────────────────────────┘  │
│                                                                │
│ ┌─────────────────────────────────────────────────────────┐  │
│ │ Advanced Options                                [▼]     │  │
│ ├─────────────────────────────────────────────────────────┤  │
│ │ ... (advanced options) ...                              │  │
│ └─────────────────────────────────────────────────────────┘  │
│                                                                │
│ [Preview Login Page]         [Save Changes]                   │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

---

## Database Schema

**No new tables required.** All settings stored in `wp_options` table.

### Option Names

```
wp_options:
- login_logo_id
- login_logo_url
- login_logo_link
- login_logo_alt
- login_hide_wp_logo
- login_center_logo
- login_logo_scale
- login_color_scheme
- login_color_primary
- login_color_secondary
- login_color_text
- login_color_link
- login_form_bg
- login_input_bg
- login_input_border
- login_bg_type
- login_bg_color
- login_bg_gradient_start
- login_bg_gradient_end
- login_bg_gradient_direction
- login_bg_image_id
- login_bg_image_url
- login_bg_position
- login_bg_size
- login_bg_repeat
- login_bg_attachment
- login_bg_overlay
- login_bg_overlay_color
- login_bg_overlay_opacity
- login_custom_css
- login_minify_css
- login_form_position
- login_form_width
- login_form_shadow
- login_form_shadow_color
- login_form_shadow_blur
- login_form_rounded
- login_form_transparent
- login_hide_rememberme
- login_hide_lostpassword
- login_hide_backtoblog
- login_apply_to_reset
- login_apply_to_register
- login_responsive
- login_responsive_logo
- login_responsive_bg
```

---

## Testing Checklist

### Functional Testing
- [ ] Logo uploads successfully
- [ ] Logo displays on login page
- [ ] Color pickers work correctly
- [ ] Colors apply to login page
- [ ] Background image uploads
- [ ] Background displays correctly
- [ ] Gradient backgrounds work
- [ ] Custom CSS applies
- [ ] Settings save correctly
- [ ] Settings load correctly
- [ ] Preview works
- [ ] Mobile responsive
- [ ] Works on password reset page
- [ ] Works on registration page

### Browser Testing
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

### Accessibility Testing
- [ ] Keyboard navigation works
- [ ] Screen reader compatible
- [ ] Color contrast meets WCAG AA standards
- [ ] Focus indicators visible
- [ ] Form labels properly associated

### Performance Testing
- [ ] CSS generation is fast (<100ms)
- [ ] Images optimized
- [ ] No layout shift
- [ ] Page load time acceptable

---

## Future Enhancements

### Phase 2 Features (Proposed)

1. **Preset Templates**
   - Professional
   - Modern
   - Minimal
   - Corporate
   - Creative

2. **Advanced Customization**
   - Custom fonts (Google Fonts integration)
   - Animation effects
   - Particle backgrounds
   - Video backgrounds

3. **Multi-Language Support**
   - Translate custom text
   - RTL language support

4. **Conditional Branding**
   - Different branding per user role
   - Different branding per OAuth2 provider
   - Time-based branding (holidays, events)

5. **Import/Export**
   - Export branding settings as JSON
   - Import branding from another site
   - Share branding templates

---

## Documentation Requirements

### User Documentation
1. Setup guide with screenshots
2. Common customization examples
3. CSS reference guide
4. Troubleshooting guide

### Developer Documentation
1. Filter reference
2. Hook reference
3. Custom theme integration guide
4. Example code snippets

---

## Risks & Mitigation

### Risk 1: CSS Conflicts with Theme
**Mitigation:** Use high-specificity selectors, !important where needed

### Risk 2: Performance Impact
**Mitigation:** Cache generated CSS, minify output, optimize images

### Risk 3: Accessibility Issues
**Mitigation:** Test with screen readers, ensure color contrast, validate HTML

### Risk 4: Browser Compatibility
**Mitigation:** Use standard CSS properties, test in all major browsers

---

## Success Criteria

- [ ] All meta boxes render correctly
- [ ] All settings save and load properly
- [ ] Branding applies to login page without errors
- [ ] No performance degradation
- [ ] No accessibility regressions
- [ ] Works on all major browsers
- [ ] Mobile responsive
- [ ] User documentation complete
- [ ] Developer documentation complete
- [ ] Zero critical bugs

---

## Timeline Estimate

| Phase | Tasks | Estimated Time |
|-------|-------|----------------|
| 1 | Settings Registration | 1-2 hours |
| 2 | UI Methods | 2-3 hours |
| 3 | Sanitization | 1 hour |
| 4 | CSS Generation & Application | 1-2 hours |
| 5 | Testing & Bug Fixes | 2-3 hours |
| 6 | Documentation | 1-2 hours |
| **Total** | | **8-13 hours** |

**Note:** Preview system (optional) adds 2-3 hours

---

## Next Steps

1. **Approval** - Get stakeholder approval for feature scope
2. **Design Review** - Review UI mockups and field specifications
3. **Implementation** - Begin Phase 1 (Settings Registration)
4. **Testing** - Comprehensive testing after Phase 4
5. **Documentation** - Create user and developer guides
6. **Release** - Include in next major version

---

**Document Version:** 1.0
**Created:** 2026-01-20
**Status:** 📋 Awaiting Approval
**Estimated Completion:** TBD
