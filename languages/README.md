# Translations - BookingPress Reviews Slider

## Available Languages

- 🇬🇧 **English (en_US)** - Default
- 🇩🇪 **German (de_DE)** - Deutsch
- 🇫🇷 **French (fr_FR)** - Français

## Files

- `bookingpress-reviews-slider.pot` - Translation template
- `bookingpress-reviews-slider-de_DE.po` - German translation (source)
- `bookingpress-reviews-slider-fr_FR.po` - French translation (source)

## Compiling Translations

### Using Poedit (Recommended)

1. Download Poedit: https://poedit.net/
2. Open `.po` file
3. Click "Save"
4. `.mo` file is automatically generated

### Using Command Line

```bash
# German
msgfmt -o bookingpress-reviews-slider-de_DE.mo bookingpress-reviews-slider-de_DE.po

# French
msgfmt -o bookingpress-reviews-slider-fr_FR.mo bookingpress-reviews-slider-fr_FR.po
```

## Activation

1. Go to **Settings → General** in WordPress
2. Select **Site Language**
3. Choose **Deutsch** or **Français**
4. Save Changes

Translations will load automatically!

## Adding New Languages

1. Copy `bookingpress-reviews-slider.pot` to new file:
   ```bash
   cp bookingpress-reviews-slider.pot bookingpress-reviews-slider-es_ES.po
   ```

2. Translate strings in the `.po` file

3. Compile to `.mo`:
   ```bash
   msgfmt -o bookingpress-reviews-slider-es_ES.mo bookingpress-reviews-slider-es_ES.po
   ```

## Need Help?

See `TRANSLATION_GUIDE.md` in the plugin root directory for complete documentation.

## Statistics

- **13 translatable strings**
- **100% translated** for German and French
- **Ready to use**

---

For more information, visit: https://awaxis.me/
