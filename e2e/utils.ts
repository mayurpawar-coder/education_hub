/**
 * Test Utilities and Constants for Education Hub E2E Tests
 */

export const BASE_URL = 'http://localhost/education_hub';

// Demo credentials from the application
export const CREDENTIALS = {
  admin: {
    email: 'admin@educationhub.com',
    password: 'password123',
    name: 'Admin User',
    role: 'admin'
  },
  teacher: {
    email: 'teacher@test.com',
    password: 'password123',
    name: 'Teacher User',
    role: 'teacher'
  },
  student: {
    email: 'raj@test.com',
    password: 'password123',
    name: 'Raj Student',
    role: 'student'
  }
};

// Page URLs
export const PAGES = {
  login: '/auth/login.php',
  register: '/auth/register.php',
  logout: '/auth/logout.php',
  dashboard: '/dashboard.php',
  adminDashboard: '/admin/dashboard.php',
  searchNotes: '/search_notes.php',
  uploadNotes: '/upload_notes.php',
  quiz: '/quiz.php',
  manageQuestions: '/manage_questions.php',
  performance: '/performance.php',
  teacherPerformance: '/teacher_performance.php',
  manageUsers: '/admin/users.php',
  manageSubjects: '/admin/subjects.php',
  notesManagement: '/notes_management.php',
  myUploads: '/my_uploads.php',
  profile: '/profile.php',
  editProfile: '/edit_profile.php'
};

// Selectors for common elements
export const SELECTORS = {
  login: {
    email: 'input#email',
    password: 'input#password',
    submit: 'button[type="submit"]',
    registerLink: 'a[href="register.php"]'
  },
  register: {
    name: 'input#name',
    email: 'input#email',
    mobile: 'input#mobile',
    role: 'select#role',
    password: 'input#password',
    confirmPassword: 'input#confirm_password',
    submit: 'button[type="submit"]',
    loginLink: 'a[href="login.php"]'
  },
  sidebar: {
    logo: '.sidebar-logo',
    dashboardLink: 'a[href*="dashboard.php"]',
    searchNotesLink: 'a[href="search_notes.php"]',
    quizLink: 'a[href="quiz.php"]',
    performanceLink: 'a[href="performance.php"], a[href="teacher_performance.php"]',
    notesManagementLink: 'a[href="notes_management.php"]',
    manageQuestionsLink: 'a[href="manage_questions.php"]',
    manageUsersLink: 'a[href="admin/users.php"]',
    manageSubjectsLink: 'a[href="admin/subjects.php"]',
    logoutLink: 'a[href*="logout.php"]',
    roleBadge: '.sidebar-logo small'
  },
  dashboard: {
    statCards: '.stat-card',
    quickActions: '.card:has(.card-title:has-text("Quick Actions"))',
    subjectsGrid: '.subjects-grid',
    subjectCards: '.subject-card'
  },
  admin: {
    statCards: '.admin-stats .stat-card, .stats-grid .stat-card',
    quickActions: '.card:has(.card-title:has-text("Admin Actions"))',
    recentUsersTable: '.card:has(.card-title:has-text("Recent Users")) table',
    allNotesTable: '.card:has(.card-title:has-text("All Uploaded Notes")) table',
    allQuestionsTable: '.card:has(.card-title:has-text("All Quiz Questions")) table'
  }
};

// Test data generators
export function generateTestUser(role: 'student' | 'teacher' = 'student') {
  const timestamp = Date.now();
  return {
    name: `Test ${role.charAt(0).toUpperCase() + role.slice(1)} ${timestamp}`,
    email: `test_${role}_${timestamp}@example.com`,
    password: 'TestPassword123',
    mobile: '+919876543210',
    role: role
  };
}

export function generateTestSubject() {
  const timestamp = Date.now();
  return {
    name: `Test Subject ${timestamp}`,
    description: `This is a test subject created at ${new Date().toISOString()}`,
    year: ['FY', 'SY', 'TY'][Math.floor(Math.random() * 3)],
    semester: Math.floor(Math.random() * 6) + 1,
    color: '#0099ff'
  };
}

export function generateTestQuestion() {
  const timestamp = Date.now();
  return {
    questionText: `Test question ${timestamp}?`,
    optionA: 'Option A',
    optionB: 'Option B',
    optionC: 'Option C',
    optionD: 'Option D',
    correctAnswer: 'A',
    difficulty: 'medium'
  };
}

export function generateTestNote() {
  const timestamp = Date.now();
  return {
    title: `Test Note ${timestamp}`,
    content: `This is test note content created at ${new Date().toISOString()}`
  };
}

// Helper function to login
export async function login(page: any, email: string, password: string) {
  await page.goto(PAGES.login, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1000);
  await page.fill(SELECTORS.login.email, email);
  await page.fill(SELECTORS.login.password, password);
  await page.click(SELECTORS.login.submit);
  await page.waitForTimeout(2000);
}

// Helper function to logout
export async function logout(page: any) {
  await page.goto(PAGES.logout, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);
}

// Helper to check if user is logged in
export async function isLoggedIn(page: any) {
  const logoutLink = await page.locator(SELECTORS.sidebar.logoutLink).count();
  return logoutLink > 0;
}

// Helper to get current user role from sidebar
export async function getCurrentRole(page: any) {
  const roleText = await page.locator(SELECTORS.sidebar.roleBadge).textContent();
  return roleText?.toLowerCase().replace(' panel', '');
}
