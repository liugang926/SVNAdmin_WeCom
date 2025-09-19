# Requirements Document

## Introduction

This specification addresses a critical bug in the SVNAdmin WeCom (Enterprise WeChat) user mapping interface where statistical charts display empty circular icons instead of actual numerical data. The statistics section should show enterprise WeChat user totals, mapped users, unmapped users, and mapping percentages to provide administrators with essential visibility into the system's user mapping status.

## Alignment with Product Vision

This bug fix directly supports the core functionality of SVNAdmin's WeCom integration by ensuring that administrators can effectively monitor and manage user mapping relationships between Enterprise WeChat and SVN systems. Accurate statistical display is essential for system administration and decision-making.

## Requirements

### Requirement 1

**User Story:** As a system administrator, I want to see accurate statistical data in the WeCom user mapping interface, so that I can monitor the current mapping status and make informed decisions about user management.

#### Acceptance Criteria

1. WHEN the user navigates to WeCom → User Mapping page THEN the system SHALL display four statistical cards with actual numerical data
2. WHEN the GetMapping API returns user data THEN the system SHALL calculate and display the total number of Enterprise WeChat users
3. WHEN user mapping data is loaded THEN the system SHALL display the count of mapped users (users with SVN accounts)
4. WHEN user mapping data is loaded THEN the system SHALL display the count of unmapped users (users without SVN accounts)
5. WHEN statistics are calculated THEN the system SHALL display the mapping percentage as a rounded integer

### Requirement 2

**User Story:** As a system administrator, I want the statistics to remain accurate regardless of applied filters, so that I always see the complete system status.

#### Acceptance Criteria

1. WHEN filters are applied to the user list THEN the statistics SHALL continue to show totals based on all users, not filtered results
2. WHEN the mapping status filter is set to "unmapped" THEN the statistics SHALL still show the complete user totals
3. WHEN search filters are applied THEN the statistics SHALL remain unchanged and reflect the full dataset

### Requirement 3

**User Story:** As a developer, I want proper error handling and debugging capabilities, so that I can troubleshoot statistical calculation issues.

#### Acceptance Criteria

1. WHEN the GetMapping API fails THEN the system SHALL display appropriate error messages
2. WHEN statistical calculations are performed THEN the system SHALL log calculation results to browser console for debugging
3. WHEN API responses are received THEN the system SHALL validate data structure before processing

## Non-Functional Requirements

### Code Architecture and Modularity
- **Single Responsibility Principle**: Statistical calculation logic should be isolated in the loadMappings method
- **Modular Design**: Statistics calculation should be separate from UI filtering logic
- **Dependency Management**: Statistics should depend only on raw API data, not filtered results
- **Clear Interfaces**: Statistical data structure should be clearly defined and consistent

### Performance
- Statistical calculations should complete within 100ms for datasets up to 1000 users
- UI updates should be immediate after data calculation

### Security
- No additional security requirements (uses existing API authentication)

### Reliability
- Statistics must be calculated correctly even with partial or malformed API data
- System should gracefully handle edge cases (zero users, all mapped, all unmapped)

### Usability
- Statistical displays should be immediately visible without scrolling
- Numbers should be formatted clearly with appropriate units (个, %)
- Visual indicators (icons and colors) should clearly distinguish different statistics