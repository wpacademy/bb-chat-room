# BuddyBoss Live Chat Room

A lightweight, secure live chat room plugin for BuddyBoss and BuddyPress community websites. Enable real-time communication between community members with built-in security features, rate limiting, and optional message encryption.

![573986121_25475116582093400_1224083023375405588_n](https://github.com/user-attachments/assets/59fb6494-32a4-4985-9d3f-400de20ed3ef)

## Features

### Core Functionality
- **Real-time Chat Interface** - Live messaging system with automatic message polling
- **Active User Tracking** - Display currently active members in the chat room
- **User Profiles Integration** - Seamless integration with BuddyBoss/BuddyPress profiles and avatars
- **Sound Notifications** - Optional audio alerts for new messages
- **Emoji Support** - Built-in emoji picker for expressive communication

### Security & Performance
- **Rate Limiting** - Prevents spam with user and IP-based rate limiting (20 messages/minute per user)
- **Message Encryption** - Optional AES-256-CBC encryption for stored messages
- **XSS Protection** - Comprehensive input sanitization and output escaping
- **Security Headers** - CSP, XSS Protection, and other security headers enabled
- **Link Restrictions** - Only administrators can post links to prevent spam
- **Spam Detection** - Built-in spam pattern detection

### Administration
- **Message Limit Control** - Set maximum message retention (optional)
- **Message Cleanup** - Automatic cleanup of old messages based on limit
- **Date Range Queries** - Retrieve messages by date for reporting
- **Activity Cleanup** - Automatic cleanup of inactive user records
- **Database Optimization** - Smart caching and efficient queries

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- BuddyBoss Platform or BuddyPress (recommended but not required)
- Users must be logged in to access the chat

## Installation

1. Download the plugin files
2. Upload the `buddyboss-live-chat` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Add the shortcode `[buddyboss_chat]` to any page or post

### Manual Database Setup (Optional)

The plugin automatically creates required database tables on activation. If tables aren't created automatically, they will be created on first use.

## Usage

### Basic Usage

Add the chat room to any page using the shortcode:

```
[buddyboss_chat]
```

### For Developers

#### Initialize Chat Room Programmatically

```php
// In your theme or plugin
echo do_shortcode('[buddyboss_chat]');
```

#### Set Message Limit

```php
use BuddyBossLiveChat\Core\Messages;

// Set maximum messages to 1000 (0 = unlimited)
Messages::get_instance()->set_max_messages(1000);

// Get current limit
$limit = Messages::get_instance()->get_max_messages();
```

#### Clear Old Messages

```php
use BuddyBossLiveChat\Core\Messages;

// Clear messages older than 30 days
Messages::get_instance()->delete_old_messages(30);

// Clear all messages (admin only)
Messages::get_instance()->clear_all_messages();
```

#### Get Message Statistics

```php
use BuddyBossLiveChat\Core\Messages;

// Get total message count
$count = Messages::get_instance()->get_message_count();

// Get messages by date range
$messages = Messages::get_instance()->get_messages_by_date('2025-01-01', '2025-01-31', 100);
```

#### Custom Rate Limiting

```php
use BuddyBossLiveChat\Core\Security;

$security = Security::get_instance();
$user_id = get_current_user_id();

if ($security->check_rate_limit($user_id)) {
    // User can send message
} else {
    // Rate limit exceeded
}
```

## File Structure

```
buddyboss-live-chat/
├── buddyboss-live-chat.php          # Main plugin file
├── includes/
│   ├── Plugin.php                    # Core plugin class
│   ├── Core/
│   │   ├── Activity.php              # User activity tracking
│   │   ├── Database.php              # Database table management
│   │   ├── Encryption.php            # Message encryption
│   │   ├── Messages.php              # Message CRUD operations
│   │   └── Security.php              # Security & validation
│   ├── Ajax/
│   │   ├── MessageHandler.php        # Message AJAX handlers
│   │   └── ActivityHandler.php       # Activity AJAX handlers
│   └── Templates/
│       └── chat-room.php             # Chat interface template
└── assets/
    ├── css/
    │   └── style.css                 # Chat room styles
    └── js/
        ├── script.js                 # Chat functionality
        └── sounds.js                 # Sound notifications
```

## Database Schema

### Messages Table (`wp_bb_chat_messages`)

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT(20) | Primary key |
| user_id | BIGINT(20) | User ID (foreign key) |
| message | TEXT | Message content (encrypted) |
| timestamp | DATETIME | Message timestamp |
| is_admin | TINYINT(1) | Admin flag |

### Activity Table (`wp_bb_chat_activity`)

| Column | Type | Description |
|--------|------|-------------|
| user_id | BIGINT(20) | Primary key |
| last_seen | DATETIME | Last activity timestamp |

## Security Features

### Message Validation
- Maximum 500 characters per message
- Sanitization using WordPress functions
- Spam pattern detection (excessive repetition, excessive caps)
- Link posting restricted to administrators only

### Rate Limiting
- **User-based**: 20 messages per minute per user
- **IP-based**: 30 messages per minute per IP address
- Transient-based implementation for performance

### Encryption
- AES-256-CBC encryption for stored messages
- Uses WordPress `NONCE_KEY` as encryption key
- Automatic fallback if encryption fails

### Headers
- Content-Security-Policy
- X-Frame-Options: SAMEORIGIN
- X-Content-Type-Options: nosniff
- X-XSS-Protection
- Referrer-Policy

## Hooks & Filters

The plugin is designed to be extensible. Common integration points:

```php
// Filter message before saving (example)
add_filter('bb_chat_before_save_message', function($message_data) {
    // Modify message data
    return $message_data;
});

// Action after message is saved (example)
add_action('bb_chat_after_save_message', function($message_id, $user_id) {
    // Custom logic after message save
}, 10, 2);
```

## Performance Optimization

- **WordPress Object Cache** - Aggressive caching of queries and results
- **Transient API** - Used for rate limiting and temporary data
- **Indexed Queries** - All database tables properly indexed
- **Lazy Loading** - Scripts only enqueued when needed
- **Query Limits** - Maximum 100 messages per request

## Troubleshooting

### Chat room not displaying
- Ensure user is logged in
- Check that shortcode is correctly placed: `[buddyboss_chat]`
- Verify database tables were created (check `wp_bb_chat_messages` and `wp_bb_chat_activity`)

### Messages not sending
- Check browser console for JavaScript errors
- Verify AJAX URL is correct
- Ensure rate limits aren't exceeded
- Check that WordPress nonce is valid

### Database tables not created
- Deactivate and reactivate the plugin
- Check file permissions
- Verify MySQL user has CREATE TABLE privileges
- Tables will auto-create on first use if activation hook failed

### Performance issues
- Enable WordPress object caching (Redis, Memcached)
- Set a message limit to prevent database bloat
- Run cleanup functions periodically
- Optimize database tables regularly

## Credits

**Author**: Mian Shahzad Raza  
**Website**: [WP Academy](https://wpacademy.pk)  
**License**: GPL v2 or later

## Support

For bug reports, feature requests, or support:
- Visit: [https://wpacademy.pk/contact](https://wpacademy.pk/contact-us/)
- Create an issue on GitHub


## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues for bugs and feature requests.

### Development Setup

1. Clone the repository
2. Install in a local WordPress environment
3. Enable `WP_DEBUG` in `wp-config.php`
4. Make your changes
5. Test thoroughly
6. Submit a pull request

## Roadmap

- [ ] Private messaging between users
- [ ] Message reactions (likes, emojis)
- [ ] File/image sharing
- [ ] Chat room moderation tools
- [ ] Multiple chat rooms
- [ ] Message editing and deletion
- [ ] User mentions (@username)
- [ ] Typing indicators
- [ ] WebSocket support for real-time updates
- [ ] Mobile app support
- [ ] Admin dashboard for analytics
