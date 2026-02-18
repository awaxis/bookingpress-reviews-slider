# BookingPress Reviews Slider

A WordPress plugin to display BookingPress staff reviews in a beautiful, responsive slider on your website.

## Features

- ✓ **Responsive Slider** - Automatically adjusts to different screen sizes
- ✓ **Customizable** - Control number of reviews, columns, ratings, and more
- ✓ **Autoplay** - Optional automatic rotation with configurable speed
- ✓ **Touch/Swipe Support** - Works perfectly on mobile devices
- ✓ **Keyboard Navigation** - Arrow keys for accessibility
- ✓ **Beautiful Design** - Clean, modern card-based layout
- ✓ **Star Ratings** - Visual star display for review ratings
- ✓ **Admin Settings** - Easy-to-use settings page
- ✓ **Shortcode Support** - Use anywhere with simple shortcode

## Installation

1. Upload the `bookingpress-reviews-slider` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Reviews Slider to configure
4. Add the shortcode to any page or post

## Usage

### Basic Shortcode

```
[bookingpress_reviews_slider]
```

### Shortcode with Parameters

```
[bookingpress_reviews_slider limit="10" min_rating="5" columns="3" autoplay="yes"]
```

### Available Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| `limit` | 10 | Number of reviews to display |
| `staff_id` | 0 | Filter by staff member (0 = all) |
| `min_rating` | 4 | Minimum rating to show (1-5) |
| `columns` | 3 | Number of columns (1-4) |
| `autoplay` | yes | Enable autoplay (yes/no) |
| `autoplay_speed` | 5000 | Autoplay speed in milliseconds |
| `show_date` | yes | Show review date (yes/no) |
| `show_title` | yes | Show review title (yes/no) |

### Examples

**Show only 5-star reviews:**
```
[bookingpress_reviews_slider min_rating="5"]
```

**Display 5 reviews in 2 columns:**
```
[bookingpress_reviews_slider limit="5" columns="2"]
```

**Show reviews for specific staff member:**
```
[bookingpress_reviews_slider staff_id="1"]
```

**Disable autoplay:**
```
[bookingpress_reviews_slider autoplay="no"]
```

## Admin Settings

Navigate to **Settings → Reviews Slider** to configure default settings:

- **Number of Reviews** - How many reviews to display
- **Staff Member** - Filter by specific staff member or show all
- **Minimum Rating** - Only show reviews with this rating or higher
- **Columns** - Number of reviews to show at once (1-4)
- **Autoplay** - Automatically rotate reviews
- **Autoplay Speed** - Time between slides in milliseconds
- **Show Date** - Display when the review was posted
- **Show Title** - Display review title (usually treatment names)

## Styling

The plugin includes default styles that work with most themes. You can customize the appearance using CSS:

### Custom CSS Examples

```css
/* Change card background */
.bpr-review-card {
    background: #f9f9f9;
}

/* Customize star color */
.bpr-star-full {
    color: #ff6b6b;
}

/* Change navigation button style */
.bpr-slider-prev,
.bpr-slider-next {
    background: #333;
    color: #fff;
}

/* Adjust card padding */
.bpr-review-card {
    padding: 40px;
}
```

## Responsive Behavior

The slider automatically adjusts columns based on screen size:

- **Mobile (≤480px):** 1 column
- **Tablet (≤768px):** 2 columns max
- **Desktop (≤1024px):** 3 columns max
- **Large Desktop (>1024px):** User-defined columns

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- BookingPress plugin installed
- jQuery (included with WordPress)

## Compatibility

- Works with any WordPress theme
- Compatible with page builders (Elementor, Divi, etc.)
- Supports WordPress Multisite
- RTL language support ready

## Development

### File Structure

```
bookingpress-reviews-slider/
├── assets/
│   ├── css/
│   │   └── reviews-slider.css
│   └── js/
│       └── reviews-slider.js
├── languages/
├── bookingpress-reviews-slider.php
├── uninstall.php
└── README.md
```

## Troubleshooting

### Slider not showing

1. Check that BookingPress plugin is installed and active
2. Verify reviews exist in the database
3. Check console for JavaScript errors
4. Ensure jQuery is loaded

### Reviews not displaying correctly

1. Check minimum rating setting
2. Verify staff member filter
3. Check database table prefix matches WordPress

### Styling issues

1. Clear browser cache
2. Check for theme CSS conflicts
3. Inspect element to see applied styles

## Support

For support, please contact: support@friseur-nabha.de

## Changelog

### Version 1.0.0 (2025-01-23)
- Initial release
- Basic slider functionality
- Admin settings page
- Responsive design
- Touch/swipe support
- Keyboard navigation
- Autoplay feature

## Credits

Developed by Awaxis

## License

GPL v2 or later

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
