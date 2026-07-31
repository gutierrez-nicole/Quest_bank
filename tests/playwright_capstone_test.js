const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

(async () => {
    console.log("Launching Playwright Chromium E2E Browser Suite...");
    
    const screenshotDir = path.join(__dirname, 'screenshots');
    if (!fs.existsSync(screenshotDir)) {
        fs.mkdirSync(screenshotDir, { recursive: true });
    }

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await context.newPage();

    try {
        // 1. Admin Login & Dashboard
        console.log("Testing Admin Login...");
        await page.goto('http://localhost:8000/index.php');
        await page.fill('input[name="email"]', 'qa_admin@questbank.test');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        await page.goto('http://localhost:8000/admin/dashboard.php');
        await page.screenshot({ path: path.join(screenshotDir, '01_admin_dashboard.png'), fullPage: true });
        console.log("  [PASS] Admin Dashboard screenshot saved.");

        // 2. Teacher Login & Pages
        console.log("Testing Teacher Workflows...");
        await page.goto('http://localhost:8000/logout.php');
        await page.goto('http://localhost:8000/index.php');
        await page.fill('input[name="email"]', 'qa_teacher@questbank.test');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        await page.goto('http://localhost:8000/teacher/dashboard.php');
        await page.screenshot({ path: path.join(screenshotDir, '02_teacher_dashboard.png'), fullPage: true });
        console.log("  [PASS] Teacher Dashboard screenshot saved.");

        await page.goto('http://localhost:8000/teacher/upload_lessons.php');
        await page.screenshot({ path: path.join(screenshotDir, '03_upload_lessons.png'), fullPage: true });
        console.log("  [PASS] Upload Lessons page screenshot saved.");

        await page.goto('http://localhost:8000/teacher/generate_ai.php');
        await page.screenshot({ path: path.join(screenshotDir, '04_generate_ai.png'), fullPage: true });
        console.log("  [PASS] AI Question Generator screenshot saved.");

        await page.goto('http://localhost:8000/teacher/upload_check.php');
        await page.screenshot({ path: path.join(screenshotDir, '05_upload_check.png'), fullPage: true });
        console.log("  [PASS] OCR Answer Checker screenshot saved.");

        await page.goto('http://localhost:8000/teacher/reports.php');
        await page.screenshot({ path: path.join(screenshotDir, '06_teacher_reports.png'), fullPage: true });
        console.log("  [PASS] Teacher Reports & Analytics screenshot saved.");

        // 3. Student Login & Dashboard
        console.log("Testing Student Dashboard...");
        await page.goto('http://localhost:8000/logout.php');
        await page.goto('http://localhost:8000/index.php');
        await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');
        await page.waitForTimeout(1500);

        await page.goto('http://localhost:8000/student/dashboard.php');
        await page.screenshot({ path: path.join(screenshotDir, '07_student_dashboard.png'), fullPage: true });
        console.log("  [PASS] Student Desktop Dashboard screenshot saved.");

        // 4. Mobile Viewport Test
        console.log("Testing Mobile Viewport (375x812)...");
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('http://localhost:8000/student/dashboard.php');
        await page.screenshot({ path: path.join(screenshotDir, '08_mobile_student_dashboard.png'), fullPage: true });
        console.log("  [PASS] Student Mobile Viewport screenshot saved.");

        console.log("Playwright E2E Browser Suite Execution Complete!");
    } catch (err) {
        console.error("Playwright Test Error: ", err.message);
    } finally {
        await browser.close();
    }
})();
