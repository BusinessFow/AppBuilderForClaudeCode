# 🧠 AppBuilder for ClaudeCode

**AppBuilder for ClaudeCode** is an advanced tool for managing software development projects with Claude CLI integration. The system enables automation of coding tasks, code refactoring, and project analysis using Claude artificial intelligence.

> 💡 **Free & Open Source** – use it, fork it, enhance it to fit your needs!

---

## 🎯 About the Project

AppBuilder for ClaudeCode is a complete environment for managing programming tasks with AI assistance. Built on the Laravel framework, the system provides:

- **Claude CLI Integration** – direct utilization of Claude's capabilities for code analysis and generation
- **Task Queue System** – asynchronous processing of multiple tasks in the background
- **Project Management** – organize work into dedicated projects with progress tracking
- **Admin Panel** – intuitive Filament-based interface for complete system management

---

## 📚 Key Features

### 🚀 Task Management
- ✅ **Task Queue for Claude** – add multiple tasks and let Claude process them automatically
- 📊 **Status Tracking** – monitor progress of each task in real-time
- 🔄 **Automatic Retries** – system automatically retries failed tasks

### 💬 Interactive Interface
- 🎨 **Modern Dashboard** – clean main panel with widgets and statistics
- 📝 **Task Editor** – built-in editor with syntax highlighting
- 📋 **Session History** – complete history of all Claude interactions

### ⚙️ Advanced Capabilities
- 🔌 **Persistent Claude CLI Process** – tasks are processed even after closing the browser
- 🧩 **Modular Architecture** – clean Laravel + Livewire structure, easy to extend
- 🔐 **Permission System** – access control and user management
- 📊 **System Logs** – detailed logging of all operations

### 🛠 Developer Tools
- 🐛 **Debug Mode** – detailed information about processed tasks
- 📦 **API Endpoints** – RESTful API for integration with external tools
- 🧪 **Unit Tests** – comprehensive Pest test suite

---

## 📸 Application Structure

### Main Dashboard
- **Statistics Widgets** – number of projects, tasks, sessions
- **Recent Activity List** – overview of latest operations
- **Quick Actions** – shortcuts to most frequently used functions

### Projects Module
- **Project List** – manage all projects
- **Project Details** – complete information about project, tasks, and sessions
- **Git Integration** – automatic retrieval of repository information

### Settings Panel
- **Claude Configuration** – customize AI behavior
- **System Settings** – timeouts, permissions, logging levels
- **User Management** – create and edit user accounts

---

## 🚀 Installation

### System Requirements
- PHP 8.1 or higher
- Laravel 10+
- Node.js 16+ with npm
- MySQL/PostgreSQL/SQLite
- Claude CLI installed globally

### Installation Steps

```bash
# Clone repository
git clone https://github.com/your-username/appbuilder-for-claudecode.git
cd appbuilder-for-claudecode

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install && npm run build

# Configure environment
cp .env.example .env
php artisan key:generate

# Configure database (edit .env)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=appbuilder
# DB_USERNAME=root
# DB_PASSWORD=

# Run database migrations
php artisan migrate

# Create administrator account
php artisan make:filament-user

# Start Claude worker (in separate terminal)
php artisan claude:worker

# Start application server
php artisan serve
```

The application will be available at: `http://localhost:8000`

---

## 🔧 Configuration

### Environment Variables (.env)
```env
# Claude CLI
CLAUDE_CLI_PATH=/usr/local/bin/claude
CLAUDE_TIMEOUT=300
CLAUDE_MAX_RETRIES=3

# Application settings
APP_TIMEZONE=UTC
APP_LOCALE=en

# System limits
MAX_CONCURRENT_TASKS=5
TASK_RETENTION_DAYS=30
```

### Claude CLI Configuration
```bash
# Install Claude CLI globally
npm install -g @anthropic-ai/claude-code

# Verify installation
claude --version
```

---

## 📚 API Documentation

### Basic Endpoints

```http
# Projects
GET    /api/projects           # List projects
POST   /api/projects           # Create project
GET    /api/projects/{id}      # Project details
PUT    /api/projects/{id}      # Update project
DELETE /api/projects/{id}      # Delete project

# Tasks
POST   /api/tasks              # Add task to queue
GET    /api/tasks/{id}/status  # Task status
GET    /api/tasks/{id}/result  # Task result

# Claude Sessions
GET    /api/sessions           # List sessions
GET    /api/sessions/{id}      # Session details
```

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Tests with code coverage
php artisan test --coverage

# Test specific module
php artisan test --filter=ProjectTest
```

---

## 🤝 Contributing

This project is open for contributions! Here's how you can help:

1. **Fork** the repository
2. Create a **branch** for your feature (`git checkout -b feature/AmazingFeature`)
3. **Commit** your changes (`git commit -m 'Add some AmazingFeature'`)
4. **Push** to the branch (`git push origin feature/AmazingFeature`)
5. Open a **Pull Request**

### Reporting Issues
Use the [Issues](https://github.com/your-username/appbuilder-for-claudecode/issues) tab to report bugs and suggest improvements.

---

## 📄 License

This project is available under the MIT License. See the [LICENSE](LICENSE) file for more details.

---

## 🙏 Acknowledgments

- Created by [@chris_collin_](https://x.com/chris_collin_)
- Built with [Laravel](https://laravel.com), [Filament](https://filamentphp.com), and [Claude CLI](https://claude.ai)
- Supported by the open source community

---

## 📞 Contact & Support

- **Documentation**: [Project Wiki](https://github.com/your-username/appbuilder-for-claudecode/wiki)
- **Questions**: [Discussions](https://github.com/your-username/appbuilder-for-claudecode/discussions)
- **Twitter**: [@chris_collin_](https://x.com/chris_collin_)

