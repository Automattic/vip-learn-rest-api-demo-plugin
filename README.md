# VIP Learn REST API Demo plugin

This is a demo plugin created for the VIP Learn Course "Advanced WordPress REST API". It demonstrates REST API concepts and best practices in WordPress.

**⚠️ IMPORTANT: This plugin is for educational purposes only and is not intended for production use.**

## Description

Version 0.1.0 demonstrates:
- Creating custom REST API endpoints
- Following WordPress REST API conventions
- Implementing proper response formatting
- Adding cache headers
- Including pagination metadata

Future versions will demonstrate additional REST API functionality.

## Course Context

This plugin is used as a teaching tool in the VIP Learn Course "Advanced WordPress REST API". Each version tag corresponds to different stages of the course, allowing students to follow along with the development process.

## REST API Endpoints

- `GET /wp-json/liveupdates/v1/posts/<post_id>` - Get recent live updates for a post
- `GET /wp-json/liveupdates/v1/posts/<post_id>/<timestamp>` - Get updates since a specific timestamp

Response format:
```json
[
  {
    "id": 123,
    "date": "2023-05-12T14:40:00+00:00",
    "modified": "2023-05-12T14:45:00+00:00",
    "title": {
      "rendered": "Update Title"
    },
    "content": {
      "rendered": "<p>Update content...</p>"
    }
  }
]
```

## Development

```bash
# Install dependencies
npm install

# Build assets
npm run build

# Watch for changes during development
npm run start
```

## Disclaimer

This plugin is provided as-is for educational purposes. It may not include all necessary security measures, optimizations, or best practices required for production environments. Do not use this plugin in production without substantial review and modifications.

## License

GPL v2 or later 