# Technology Stack

## Project Type
Web-based SVN repository management system with enterprise WeChat integration - a PHP web application providing centralized Subversion server administration with real-time collaboration features.

## Core Technologies

### Primary Language(s)
- **Language**: PHP 7.2+ (compatible with PHP 8.x)
- **Runtime**: Apache HTTP Server with mod_php or PHP-FPM
- **Language-specific tools**: Composer (dependency management), PHP CLI for daemon processes

### Key Dependencies/Libraries
- **Subversion**: SVN command-line tools and libraries for repository operations
- **Apache HTTP Server**: Web server with mod_dav_svn for SVN HTTP protocol support
- **Database Layer**: PDO with SQLite (default) or MySQL support
- **Enterprise WeChat SDK**: Official WeChat Work API SDK for PHP
- **cURL**: HTTP client for API communications
- **JSON**: Data exchange format for API responses and configuration

### Application Architecture
**Multi-tier Architecture with Daemon Services:**
- **Web Layer**: PHP-based web interface for administration and monitoring
- **Service Layer**: Background daemon (svnadmind.php) for system operations and synchronization
- **Data Layer**: File system (SVN repositories) + Database (user/permission management)
- **Integration Layer**: Enterprise WeChat API client for real-time synchronization
- **Notification Layer**: WeChat Work webhook system for real-time notifications

### Data Storage
- **Primary storage**: 
  - SVN repositories: File system (/home/svnadmin/rep/)
  - User/permission data: SQLite (default) or MySQL database
  - Configuration: PHP configuration files and JSON templates
- **Caching**: File-based caching for WeChat access tokens and user data
- **Data formats**: JSON for API communication, SVN authz format for permissions, PHP arrays for configuration

### External Integrations
- **APIs**: 
  - Enterprise WeChat Work API (contacts, departments, messages)
  - SVN command-line interface via PHP exec()
- **Protocols**: 
  - HTTP/HTTPS for web interface and WeChat API
  - SVN protocol and HTTP DAV for repository access
- **Authentication**: 
  - Enterprise WeChat OAuth 2.0 for user authentication
  - SVN authz file-based permission system
  - LDAP/AD integration (existing feature)

### Monitoring & Dashboard Technologies
- **Dashboard Framework**: PHP-generated HTML with JavaScript for dynamic updates
- **Real-time Communication**: AJAX polling and WebSocket support for live updates
- **Visualization Libraries**: Chart.js for statistics, custom CSS for repository status
- **State Management**: PHP session management with database persistence

## Development Environment

### Build & Development Tools
- **Build System**: No complex build process - direct PHP deployment
- **Package Management**: Composer for PHP dependencies
- **Development workflow**: 
  - Direct file editing with immediate reflection
  - PHP built-in server for development
  - Docker Compose for containerized development

### Code Quality Tools
- **Static Analysis**: PHP_CodeSniffer for code style
- **Formatting**: PSR-12 coding standard compliance
- **Testing Framework**: PHPUnit for unit testing (to be implemented)
- **Documentation**: Inline PHP documentation and README files

### Version Control & Collaboration
- **VCS**: Git (ironically managing an SVN management system)
- **Branching Strategy**: Feature branches with main/master trunk
- **Code Review Process**: Pull request-based review workflow

### Dashboard Development
- **Live Reload**: Manual refresh or AJAX-based updates
- **Port Management**: Configurable Apache virtual hosts
- **Multi-Instance Support**: Multiple virtual host configurations

## Deployment & Distribution

- **Target Platform(s)**: 
  - Linux servers (CentOS 7+, Ubuntu 18.04+)
  - Docker containers (recommended for easy deployment)
- **Distribution Method**: 
  - Git repository clone and manual setup
  - Docker image with pre-configured environment
- **Installation Requirements**: 
  - PHP 7.2+, Apache HTTP Server, Subversion 1.8+
  - Database (SQLite included, MySQL optional)
  - Network access to Enterprise WeChat API endpoints
- **Update Mechanism**: Git pull with database migration scripts

## Technical Requirements & Constraints

### Performance Requirements
- **Response time**: Web interface < 2 seconds for most operations
- **Synchronization**: WeChat organization sync < 5 minutes for full update
- **Notification delivery**: Real-time notifications within 30 seconds
- **Concurrent users**: Support 50+ simultaneous web users
- **Repository operations**: No performance impact on SVN operations

### Compatibility Requirements  
- **Platform Support**: Linux (CentOS 7+, Ubuntu 18.04+, RHEL 7+)
- **Dependency Versions**: 
  - PHP 7.2 - 8.2
  - Apache 2.4+
  - Subversion 1.8+
  - MySQL 5.7+ or SQLite 3.7+
- **Standards Compliance**: 
  - SVN protocol compliance
  - Enterprise WeChat API specifications
  - HTTP/1.1 and HTTP/2 support

### Security & Compliance
- **Security Requirements**: 
  - HTTPS for web interface and API communications
  - Secure token storage for WeChat API credentials
  - SVN repository access control via authz files
  - Input validation and SQL injection prevention
- **Compliance Standards**: Enterprise data protection requirements
- **Threat Model**: 
  - Protect against unauthorized repository access
  - Secure API credential management
  - Prevent privilege escalation through web interface

### Scalability & Reliability
- **Expected Load**: 
  - 10-100 SVN repositories
  - 50-500 users across multiple departments
  - 100-1000 commits per day
- **Availability Requirements**: 99.5% uptime, graceful degradation if WeChat API unavailable
- **Growth Projections**: Horizontal scaling through multiple instance deployment

## Technical Decisions & Rationale

### Decision Log
1. **PHP as Primary Language**: 
   - **Rationale**: Maintains compatibility with existing SvnAdminV2.0 codebase
   - **Alternatives**: Rewrite in Python/Node.js considered but rejected due to migration complexity
   - **Trade-offs**: Accepted PHP limitations for faster development and deployment

2. **Daemon Process Architecture**: 
   - **Rationale**: Background synchronization prevents web interface blocking
   - **Implementation**: svnadmind.php daemon handles WeChat API calls and sync operations
   - **Benefits**: Improved user experience and system reliability

3. **File-based SVN Configuration**: 
   - **Rationale**: Leverages existing SVN authz system for permission management
   - **Integration**: Dynamic generation of authz files based on WeChat organization data
   - **Advantage**: No modification of SVN server core required

4. **Hybrid Database Approach**: 
   - **Rationale**: SQLite for simplicity, MySQL for enterprise scalability
   - **Implementation**: PDO abstraction layer supports both seamlessly
   - **Flexibility**: Allows deployment scaling based on organization size

5. **Enterprise WeChat API Integration**: 
   - **Rationale**: Native Chinese enterprise collaboration platform
   - **Implementation**: Official SDK with custom wrapper for specific use cases
   - **Benefits**: Seamless integration with existing enterprise workflows

## Known Limitations

- **Single Server Architecture**: Current design doesn't support multi-server clustering
  - **Impact**: Limited to single-point-of-failure scenarios
  - **Future Solution**: Consider load balancer and shared storage implementation

- **PHP Performance for Large Organizations**: 
  - **Impact**: May require optimization for 1000+ user organizations
  - **Mitigation**: Implement caching and batch processing for large sync operations

- **WeChat API Rate Limits**: 
  - **Impact**: Synchronization speed limited by API quotas
  - **Solution**: Implement intelligent batching and incremental sync strategies

- **SVN Protocol Limitations**: 
  - **Impact**: Bound by Subversion 1.8+ feature set
  - **Context**: Acceptable trade-off for enterprise environments still using SVN