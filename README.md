# 🌿 Haven

### Anonymous Mental Wellness & Mood-Sharing Community

> **A place to be heard.**

Haven is a modern, AI-assisted mental wellness and mood-sharing community designed to give people a safe place to express their feelings, connect with others, discover helpful resources, and receive timely human support.

The platform combines **social networking, mood tracking, AI assistance, community moderation, volunteer support, wellness articles, realtime interaction, and personalized recommendations** into one ecosystem.

> ⚠️ **Haven is not a replacement for professional mental-health care. AI-generated responses are supportive suggestions, not medical diagnoses or treatment.**

---

## ✨ What Makes Haven Different?

Haven isn't designed as just another social-media platform.

It combines:

```text
                    🌿 HAVEN
                       │
       ┌───────────────┼────────────────┐
       │               │                │
       ▼               ▼                ▼
   Community          AI            Human Support
       │               │                │
       ├── Posts       ├── MindGuide    ├── Volunteers
       ├── Comments    ├── MindShield   ├── Moderators
       ├── Reactions   └── MindInsight  └── Escalation
       │
       ▼
    Wellness
       │
       ├── Articles
       ├── Mood Tracking
       ├── Resources
       ├── Reels
       └── Recommendations
```

The central philosophy is:

> **AI can respond immediately, but humans remain essential.**

---

# 🧠 Three AI Systems

Haven uses three specialized AI roles.

## 🛡️ MindShield AI

### Safety & Risk Analysis

MindShield analyzes community content for potentially concerning patterns.

It can identify signals related to:

* Emotional distress
* Hopelessness
* Loneliness
* Bullying
* Harassment
* Academic stress
* Burnout
* Anxiety-related language
* Potential self-harm indicators
* Other moderation concerns

Example:

```text
AI SAFETY ANALYSIS

Risk Score       82/100
Risk Level      HIGH

Detected:
✓ Emotional distress
✓ Hopelessness
✓ Isolation-related language

Suggested Action:
→ Supportive AI response
→ Volunteer notification
→ Moderator review
```

MindShield does **not diagnose users**.

---

# 🌱 MindGuide AI

### Supportive AI Companion

MindGuide is the main user-facing AI assistant.

It can:

* Listen to users
* Have supportive conversations
* Ask gentle follow-up questions
* Suggest coping and wellness activities
* Recommend Haven articles
* Recommend resources
* Help users organize their thoughts
* Perform mood check-ins
* Summarize conversations
* Suggest contacting a human volunteer when appropriate

MindGuide clearly identifies itself as AI.

---

# 🧠 MindInsight AI

### Personalization & Wellness Analytics

MindInsight analyzes non-diagnostic activity patterns to personalize the Haven experience.

It can identify:

* Frequently viewed topics
* Article interests
* Mood-tracking patterns
* Community interests
* Resource usage
* Engagement patterns

Example:

```text
YOUR HAVEN INSIGHT

You have recently explored:

• Study stress
• Sleep
• Time management

Recommended:
"Managing Academic Pressure"
```

MindInsight should never present its observations as medical diagnoses.

---

# 🚨 AI → Human Support

One of Haven's most important features is its AI-to-human escalation system.

```text
                    User Post
                        │
                        ▼
                  MindShield AI
                        │
                        ▼
                   Risk Analysis
                        │
          ┌─────────────┼─────────────┐
          ▼             ▼             ▼
        LOW           HIGH         CRITICAL
          │             │             │
          ▼             ▼             ▼
      Normal Feed   Volunteer      Priority
                    Notification   Human Review
                        │             │
                        └──────┬──────┘
                               ▼
                           Moderator
```

The goal is not to let AI "handle" vulnerable users alone.

Instead:

> **AI detects → AI supports → Humans review when necessary.**

---

# 👥 Volunteer System

Haven includes a dedicated volunteer environment.

Volunteers can:

* See assigned cases
* Respond to users
* View AI-generated case summaries
* Mark cases as handled
* Add private notes
* Escalate cases
* Set availability
* Track response times
* Receive high-priority notifications

Example:

```text
🚨 HIGH PRIORITY CASE

Risk: 82%

Topic:
Academic + Emotional Distress

AI Summary:
User appears overwhelmed by academic pressure.

Recommended:
Human response recommended.
```

---

# 🛡️ Moderator Dashboard

`moderator.php`

Moderators maintain community safety.

### Features

* Moderation queue
* AI flagged posts
* User reports
* Reported comments
* High-risk cases
* User history
* Volunteer assignment
* Content removal
* Warnings
* Temporary restrictions
* Account suspension
* Case escalation
* Moderator notes
* AI explanations
* Moderation history
* Community health analytics

---

# 👨‍💼 Admin Dashboard

`admin.php`

Administrators control the overall platform.

### Management

* Users
* Moderators
* Volunteers
* Roles & permissions
* Posts
* Articles
* Resources
* Reports
* AI settings
* Categories
* Languages
* Notifications
* Platform settings
* Analytics
* Audit logs

---

# 🌍 Public Homepage

Visitors do not need an account to explore Haven.

The public experience includes:

* Introduction to Haven
* Public community posts
* Wellness tips
* Daily recommendations
* Articles
* Mood-related resources
* Public reels
* Community statistics
* AI introduction
* Volunteer information

However, actions such as commenting, reacting, posting, chatting, bookmarking, and using personalized AI features require authentication.

---

# 🏠 Community Feed

`feed.php`

The feed is designed as a realtime social experience.

### Posts can contain

* Text
* Mood tags
* Images
* Audio
* Video
* Polls
* YouTube embeds
* Google Maps/location
* Articles
* Wellness resources

### Interactions

* Support reactions
* Comments
* Replies
* Bookmarks
* Shares
* Reports
* AI responses
* Volunteer responses

---

# ♾️ Infinite Scrolling

The feed doesn't require traditional page navigation.

```text
Load Posts
    ↓
User Scrolls
    ↓
Intersection Observer
    ↓
AJAX Request
    ↓
PHP API
    ↓
MySQL
    ↓
JSON
    ↓
New Posts
    ↓
GSAP Animation
```

A "new posts" indicator can appear without unexpectedly moving the user's feed.

---

# 💬 Realtime Interaction

Haven provides realtime-style interaction without requiring an expensive server infrastructure.

Supported features include:

* Live comments
* Comment replies
* Reaction updates
* Typing indicators
* Notifications
* New post notifications
* Poll updates
* Volunteer responses
* AI responses
* Online presence

For cPanel/shared hosting, the initial implementation uses **AJAX polling** where persistent WebSocket infrastructure isn't available.

---

# ❤️ Supportive Reactions

Instead of a traditional dislike button, Haven uses emotionally appropriate reactions.

Examples:

```text
❤️ Support

🤝 I Understand

🫂 Hug

🌸 Stay Strong

🙏 Prayers

💙 Helpful

👏 Brave

🌞 Inspiring
```

The reaction system can be managed from the admin dashboard.

---

# 👤 Public Profiles

`profile.php`

Profiles provide a Facebook-style social experience adapted for Haven.

### Profile

* Avatar
* Cover image
* Bio
* Interests
* Posts
* Photos
* Videos
* Reels
* Friends
* Followers
* Following
* Achievements
* Communities
* Shared resources

### Social actions

* Add Friend
* Accept Request
* Reject Request
* Follow
* Unfollow
* Message
* Share Profile
* Mute
* Restrict
* Block
* Report Profile

---

# 🎥 Wellness Reels

Haven includes short-form video content focused on wellness rather than entertainment alone.

Users can share:

* Study tips
* Breathing exercises
* Meditation
* Motivation
* Healthy habits
* Recovery stories
* Positive messages

### Features

* Vertical video
* Swipe navigation
* Captions
* Support reactions
* Comments
* Sharing
* Saving
* AI moderation

---

# 📅 Mood Tracking

Users can maintain a personal mood calendar.

```text
MON     🙂 Calm
TUE     😔 Sad
WED     😟 Stressed
THU     😊 Happy
FRI     😐 Neutral
```

The datepicker allows users to explore historical mood entries.

Mood data can also power personalized recommendations while respecting privacy.

---

# 📰 Wellness Articles

Haven contains a dedicated wellness library.

## `articles.php`

A calm digital wellness magazine featuring:

* Featured articles
* Categories
* Search
* Trending articles
* Latest articles
* Recommended articles
* Reading time
* Bookmarks
* Personalized recommendations

## `article.php`

A distraction-free reading experience with:

* Reading progress
* Table of contents
* AI summary
* Author information
* Bookmarking
* Sharing
* Comments
* Related articles
* Recommended resources
* Font-size controls
* Reading mode

### Article design

Unlike the community interface, articles use:

* Cream/white backgrounds
* Minimal colors
* Calm typography
* Soft borders
* Subtle GSAP animation

---

# 🤖 AI Chatbot

`chatbot.php`

The dedicated MindGuide interface provides:

* AI conversations
* Conversation history
* New conversations
* AI typing animation
* Suggested prompts
* Mood check-ins
* Wellness recommendations
* Article recommendations
* Conversation summaries
* Save/delete conversations
* Volunteer handoff

---

# 🚩 Reporting System

Users can report:

### Posts

* Harassment
* Bullying
* Spam
* Hate
* Unsafe content
* Misinformation
* Other

### Comments

The same reporting workflow applies.

### Profiles

* Fake account
* Impersonation
* Harassment
* Spam
* Bullying
* Other

Users can also:

* Block
* Mute
* Restrict

---

# 🔐 Authentication

Users must create an account for interactive features.

Registration includes:

* Unique username
* Realtime username availability
* Email verification
* Password strength validation
* Secure password hashing
* Profile avatar
* Age
* Basic profile information
* Language
* Interests
* Privacy preferences

Unverified email accounts cannot log in.

---

# 📧 Email Verification

Registration flow:

```text
Register
   ↓
Validate Information
   ↓
Create Account
   ↓
Send Verification Email
   ↓
User Clicks Link
   ↓
Email Verified
   ↓
Account Activated
   ↓
Login
```

---

# 🌐 Multi-Language Support

Haven is designed for multilingual communities.

Initial target languages can include:

* 🇬🇧 English
* 🇧🇩 বাংলা
* 🇮🇳 Hindi
* 🇵🇰 Urdu
* 🇸🇦 Arabic

Language support covers:

* Interface
* Articles
* Categories
* AI responses
* Notifications
* Resources
* Community content where applicable

---

# 📍 Location & Maps

Posts can optionally include location information.

Supported:

* Google Maps iframe
* Embedded location
* Place information

Location sharing is optional and controlled by the user.

---

# 🎵 Media

Haven supports:

* Image uploads
* Audio uploads
* Video uploads
* YouTube embeds
* External media

Uploaded files are validated before storage.

---

# 🎨 UI/UX

Haven intentionally uses different visual identities for different modules.

## Community

* Glassmorphism
* Soft gradients
* Interactive cards
* GSAP animations
* Three.js effects
* Floating particles
* Responsive layouts

## Articles

* Cream/white
* Minimal colors
* Calm typography
* Subtle animation
* Distraction-free reading

## AI

* Soft futuristic interface
* Animated AI states
* Calm gradients
* Conversational UI

## Admin / Moderator

* Professional dashboards
* Charts
* Tables
* Clear status indicators
* Information-dense layouts

---

# ✨ Frontend Technology

| Technology            | Purpose                |
| --------------------- | ---------------------- |
| HTML5                 | Structure              |
| CSS3                  | Styling                |
| SASS/SCSS             | Modular styling        |
| JavaScript            | Interactivity          |
| GSAP                  | Animations             |
| ScrollTrigger         | Scroll effects         |
| Three.js              | 3D effects             |
| Lottie                | Micro animations       |
| AJAX                  | Realtime communication |
| Fetch API             | API requests           |
| Intersection Observer | Infinite scrolling     |
| Chart.js              | Analytics              |
| SVG                   | Graphics               |

---

# ⚙️ Backend

Haven is designed specifically to work with **PHP + MySQL on cPanel/shared hosting**.

### Backend technologies

```text
PHP 8.x
MySQL
REST APIs
AJAX
JSON
Sessions
```

---

# 🗂️ Project Structure

The project intentionally keeps the main modules easy to understand.

```text
haven/
│
├── index.php
├── feed.php
├── community.php
├── post.php
├── profile.php
├── chatbot.php
├── articles.php
├── article.php
├── volunteer.php
├── moderator.php
├── admin.php
├── register.php
├── login.php
│
├── api/
│   ├── auth/
│   ├── posts/
│   ├── comments/
│   ├── reactions/
│   ├── chat/
│   ├── profile/
│   ├── moderation/
│   └── notifications/
│
├── includes/
│   ├── config.php
│   ├── database.php
│   ├── auth.php
│   ├── session.php
│   ├── security.php
│   └── functions.php
│
├── assets/
│   ├── css/
│   ├── scss/
│   ├── js/
│   ├── images/
│   └── fonts/
│
├── ai/
│   ├── mindshield/
│   ├── mindguide/
│   └── mindinsight/
│
├── uploads/
│   ├── images/
│   ├── videos/
│   └── audio/
│
└── database/
    └── schema.sql
```

---

# 🗄️ Database Modules

Major database entities include:

```text
users
user_profiles
user_settings

friend_requests
friends
followers
blocked_users
muted_users

posts
post_media
post_reactions
post_views
post_bookmarks
post_reports

comments
comment_reactions
comment_reports

chat_sessions
chat_messages

ai_post_analysis
ai_flags
risk_history

volunteers
volunteer_cases
volunteer_messages

moderators
moderation_queue
moderation_actions
moderation_logs

articles
article_categories
article_views
article_bookmarks
article_comments

notifications

mood_entries
mood_categories

reels
reel_views
reel_reactions

reports
report_evidence

languages
translations

audit_logs
```

---

# 🔄 Post Processing

A typical post follows this workflow:

```text
User Creates Post
       ↓
PHP Validation
       ↓
MySQL
       ↓
MindShield Analysis
       ↓
Emotion/Risk Classification
       ↓
MindGuide Supportive Response
       ↓
Community Feed
       ↓
Reactions / Comments
       ↓
Volunteer Review if Necessary
       ↓
Moderator Review if Necessary
       ↓
Analytics
```

---

# 🔐 Security

Because Haven deals with sensitive user-generated content, security is a core requirement.

The application should implement:

* Password hashing
* Prepared SQL statements
* CSRF protection
* XSS prevention
* Input validation
* Output escaping
* Session regeneration
* Secure cookies
* Rate limiting
* File upload validation
* MIME validation
* Permission checks
* Role-based access control
* Audit logging
* Abuse prevention

Sensitive user information should only be accessible to authorized roles.

---

# 📊 Analytics

Haven can provide analytics for administrators and moderators.

Examples:

```text
Total Users

Active Users

Posts Today

Comments Today

Support Reactions

AI Responses

Volunteer Responses

Reports

Resolved Cases

Average Volunteer Response Time

Community Mood Trends

Article Views

Popular Topics
```

---

# 🔌 API Architecture

The application uses RESTful APIs for major operations.

Examples:

```text
/api/auth/
/api/posts/
/api/comments/
/api/reactions/
/api/profile/
/api/chat/
/api/notifications/
/api/moderation/
/api/volunteers/
```

A small **GraphQL module** can also be included as an academic requirement for demonstrating alternative API querying.

---

# 📚 Academic Requirements

Haven is designed to incorporate the requested course requirements:

* [x] ERD
* [x] Realtime commenting
* [x] Reaction system
* [x] Realtime username verification
* [x] Email verification
* [x] Strong password validation
* [x] Multiple text fields
* [x] Multiple dropdowns
* [x] Interactive rating
* [x] Pagination
* [x] Infinite scrolling
* [x] External audio/video
* [x] YouTube embedding
* [x] Google Maps
* [x] Session management
* [x] TensorFlow Lite
* [x] Image/file uploads
* [x] Datepicker
* [x] GSAP
* [x] SASS
* [x] Git workflow
* [x] RESTful APIs
* [x] GraphQL
* [x] Admin dashboard
* [x] Moderator dashboard
* [x] Volunteer system
* [x] AI-assisted analysis
* [x] Realtime notifications
* [x] Social profile system
* [x] Reporting & blocking
* [x] Mood tracking

---

# 🤖 AI Architecture

```text
                    HAVEN
                      │
                 PHP API Layer
                      │
              AI Service Layer
                      │
       ┌──────────────┼──────────────┐
       │              │              │
       ▼              ▼              ▼
 MindShield       MindGuide      MindInsight
 Safety           Support        Analytics
       │              │              │
       └──────────────┼──────────────┘
                      ▼
                 MySQL / App
```

AI credentials must never be exposed in frontend JavaScript.

---

# 📱 Responsive Design

Haven is designed for:

* Desktop
* Laptop
* Tablet
* Mobile

Mobile navigation:

```text
🏠 Home
👥 Community
＋ Create
🤖 AI
👤 Profile
```

---

# 🚀 Installation

## 1. Clone Repository

```bash
git clone https://github.com/YOUR_USERNAME/haven.git
cd haven
```

## 2. Create MySQL Database

Using cPanel:

```text
cPanel
  ↓
MySQL Databases
  ↓
Create Database
  ↓
phpMyAdmin
  ↓
Import database/schema.sql
```

## 3. Configure Database

Configure:

```text
DB_HOST
DB_NAME
DB_USER
DB_PASSWORD
```

## 4. Configure Email

Configure SMTP for:

* Registration verification
* Password reset
* Security notifications

## 5. Configure External Services

Depending on enabled modules:

```text
Google Maps
AI Service
Email Provider
YouTube
```

## 6. Upload to cPanel

Upload the project to:

```text
public_html/
```

Configure the appropriate PHP version and database credentials.

---

# 🔑 Environment Configuration

Example:

```env
APP_ENV=production

DB_HOST=localhost
DB_NAME=your_database
DB_USER=your_user
DB_PASSWORD=your_password

MAIL_HOST=
MAIL_USERNAME=
MAIL_PASSWORD=

AI_API_KEY=

GOOGLE_MAPS_KEY=
```

**Never commit real API keys, database passwords, SMTP passwords, or other secrets to GitHub.**

---

# 🧪 Git Workflow

Recommended workflow:

```bash
git checkout -b feature/community-feed

git add .

git commit -m "Add realtime community feed"

git push origin feature/community-feed
```

Then create a Pull Request.

---

# 🗺️ Development Roadmap

### Phase 1 — Foundation

* Database
* Authentication
* Email verification
* Basic profiles
* Core UI

### Phase 2 — Community

* Posts
* Media
* Reactions
* Comments
* Infinite scrolling

### Phase 3 — Realtime

* AJAX updates
* Typing indicators
* Notifications
* Live reactions
* Polls

### Phase 4 — AI

* MindShield
* MindGuide
* MindInsight
* Risk analysis
* AI responses

### Phase 5 — Human Support

* Volunteers
* Cases
* Escalation
* Moderator workflow

### Phase 6 — Content

* Articles
* Resources
* Reels
* Search
* Recommendations

### Phase 7 — Advanced UI

* GSAP
* Three.js
* SASS
* Responsive mobile UI

### Phase 8 — Testing & Security

* Security testing
* Performance testing
* AI evaluation
* Moderation testing
* Accessibility
* Database optimization

---

# ⚠️ Mental Health & Safety Disclaimer

Haven is an educational/software engineering project and community-support platform.

It is **not a medical or clinical service**.

AI responses and risk classifications are probabilistic and may be incorrect. They should not be treated as professional diagnosis, medical advice, or treatment.

Haven's safety architecture therefore prioritizes:

```text
AI assistance
      +
Human volunteers
      +
Human moderators
      +
Appropriate professional/emergency resources
```

The platform should never falsely claim that an AI system can guarantee a user's safety.

---

# 🤝 Contributing

Contributions are welcome.

```text
Fork
 ↓
Create Branch
 ↓
Make Changes
 ↓
Test
 ↓
Commit
 ↓
Push
 ↓
Pull Request
```

When contributing, please preserve Haven's:

* Privacy principles
* Safety principles
* Accessibility
* Moderation standards
* Responsible AI guidelines

---

# 📄 License

This project is currently being developed as an academic and software-engineering project.

An appropriate open-source license should be added before publicly distributing the complete source code.

---

# 🌿 Haven

### **A place to be heard.**

```text
Listen.
Support.
Connect.
Grow.
```

Built with:

**PHP · MySQL · JavaScript · SASS · GSAP · Three.js · TensorFlow Lite · REST APIs · GraphQL**

---

### Project Status

🚧 **Currently in active development**

The architecture and features are evolving as the project progresses.
