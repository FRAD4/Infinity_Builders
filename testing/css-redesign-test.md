```markdown
# CSS Redesign - Test Checklist

## Theme Toggle
- [ ] Click toggle on dashboard.php - theme switches to light
- [ ] Knob animates to the right when light mode active
- [ ] Refresh page - light mode persists
- [ ] Click toggle again - returns to dark
- [ ] Open in new tab - theme persists
- [ ] Works on ALL pages: dashboard, projects, vendors, users, email, reports, settings

## Sidebar Navigation
- [ ] Dashboard link works
- [ ] Projects link works
- [ ] Vendors link works
- [ ] Email link works (navigates to email.php)
- [ ] Reports link works (navigates to reports.php)
- [ ] Settings link works (navigates to settings.php)
- [ ] Users visible ONLY for admin users

## Active State Styling
- [ ] Dashboard shows orange active indicator when on dashboard
- [ ] Projects shows orange when on projects
- [ ] Other pages show orange when active
- [ ] Active background is rgba(244, 121, 32, 0.1)
- [ ] Active text is #F47920

## Visual Check
- [ ] Sidebar is #1F2937 (blue-gray) in BOTH light and dark mode
- [ ] Dark mode: main area #111827
- [ ] Light mode: main area #F3F4F6
- [ ] Cards are #1F2937 (dark) / #FFFFFF (light)
- [ ] Orange accent (#F47920) appears on buttons and active states
- [ ] Stats cards have blue gradient

## Logout Flow
- [ ] Click logout in sidebar
- [ ] Session destroyed
- [ ] Redirected to login.php

## New Pages
- [ ] settings.php renders correctly
- [ ] reports.php renders correctly
- [ ] email.php renders with vendor dropdown
- [ ] email.php - select vendor auto-fills email field

## Mobile Responsive
- [ ] Sidebar collapses on mobile
- [ ] Stats cards stack on small screens
- [ ] Tables are scrollable horizontally
```
