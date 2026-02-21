Playwright E2E tests for Education Hub

Prerequisites
- Node.js (16+)
- The Education Hub app running under XAMPP at: http://localhost/education_hub%20-%20Copy

Install and run tests

1. Install dev dependencies:

   cd tests
   npm install

2. Install Playwright browsers:

   npm run test:install

3. Run tests:

   npm test

Configuration
- To override the base URL, set the `BASE_URL` environment variable before running tests. Example:

  BASE_URL="http://localhost/education_hub%20-%20Copy" npm test

Notes
- Tests use seeded demo accounts from `database/education_hub.sql`:
  - Admin: admin@educationhub.com / password123
  - Teacher: teacher@test.com / password123
  - Student: raj@test.com / password123
