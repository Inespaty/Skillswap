# SkillSwap Project Defense Guide

This guide is designed to help you explain and defend your SkillSwap project during a presentation or viva. It covers the technical architecture, key implementation details, security measures, and answers to common questions teachers might ask.

## 1. Project Overview
SkillSwap is a web-based platform that facilitates skill exchange between users using a credit-based system.
- **Core Concept:** Users earn credits by teaching skills and spend credits to learn new skills.
- **Tech Stack:**
  - **Frontend:** HTML5, CSS3 (Bootstrap 5), JavaScript (Vanilla ES6+).
  - **Backend:** PHP (Native).
  - **Database:** MySQL.
  - **Architecture:** Client-Server model using fetch API for asynchronous communication.

## 2. Key Technical Implementations

### Authentication System
- **Session-Based:** Uses PHP native sessions (`session_start()`).
- **Client-Side:** `auth.js` manages the user state on the frontend. It checks for a valid session on page load (`whoami.php`).
- **Security:** Passwords are hashed using `password_hash()` (Bcrypt) before storage. `password_verify()` checks credentials.

### The Credit System (Double-Entry Bookkeeping)
- Credits are the currency of the platform.
- **Transaction Logic:** When a request is confirmed as completed:
  1.  Credits are deducted from the **Requester**.
  2.  Credits are added to the **Provider**.
  3.  A record is created in the `transactions` table to track this movement (Audit trail).
- **Atomic Operations:** Database transactions are used (where applicable) to ensure money isn't lost if an error occurs halfway through.

### Real-Time Chat (Polling Architecture)
- **Mechanism:** The frontend uses **Short Polling**.
- **Implementation:** `messages.js` sets a `setInterval` (every 3 seconds) to call `get_messages.php`.
- **Optimization:** It checks the `last_message_id`. The server only returns *new* messages effectively (or the frontend filters them), reducing bandwidth usage compared to reloading the whole page.

### Dynamic Dashboard & UI used
- **AJAX/Fetch:** Pages like the dashboard (`admin-dashboard.js`, `dashboard.js`) load data asynchronously. This prevents full page reloads and makes the app feel faster and "app-like".
### Custom Toast Notification System (showToast)
Instead of using the browser's native blocking `alert()` function, we implemented a non-blocking "Toast" system.
-   **Core Logic:**
    1.  **Dynamic Container:** The function first checks if a fixed container (`#toast-container`) exists in the DOM. If not, it creates and appends it to the document body.
    2.  **Element Injection:** It constructs a standard Bootstrap Toast HTML structure programmatically, applying dynamic classes (e.g., `bg-success` for success, `bg-danger` for errors).
    3.  **Global Access:** The function is attached to the `window` object (`window.showToast`), making it accessible from any script or HTML event handler without imports.
    4.  **Auto-Cleanup:** A wrapper around `bootstrap.Toast` handles the showing animation, and a `setTimeout` ensures the DOM element is completely removed after 3 seconds to preserve memory.
-   **Defense Point:** This demonstrates knowledge of **DOM manipulation** and **Asynchronous UI updates**, providing a much smoother User Experience (UX) than halting code execution with `alert()`.

## 3. Security Measures (Crucial for Defense)

*   **SQL Injection Prevention:**
    *   **How:** We use **Prepared Statements** (`$stmt = $pdo->prepare(...)`) for ALL database queries involving user input.
    *   **Why:** This separates the SQL code from the data, making it impossible for hackers to inject malicious SQL commands.

*   **XSS (Cross-Site Scripting) Protection:**
    *   **How:** Output escaping (e.g., `htmlspecialchars` in PHP or simple text content assignment in JS) when displaying user-generated content like reviews or messages.

*   **Access Control:**
    *   Backend checks (e.g., `if (!isset($_SESSION['user_id'])) die('Unauthorized');`) ensure users can't access protected API endpoints without logging in.
    *   Admin files (`admin.html`) have specific checks to ensure `is_admin` is true.

## 4. Potential Defense Questions & Answers

**Q1: Why did you choose Vanilla PHP/JS instead of a framework like Laravel or React?**
*   **Answer:** "I chose vanilla technologies to demonstrate a deep understanding of web fundamentals. Frameworks abstract away a lot of logic (like session management, DOM manipulation, and database connections). Building it from scratch proves I understand how these core mechanisms work under the hood."

**Q2: How do you handle two users booking the same slot/skill at the same time?**
*   **Answer:** "Currently, the system allows multiple requests. However, the database enforces unique constraints where logical. If we were to implement strict slot booking, we would use database **transactions** and **row locking** to ensure consistency."

**Q3: Is the credit data secure? Can a user manually edit their credits?**
*   **Answer:** "No, credits are stored in the server-side database. The frontend only *displays* the value. A user might inspect details to change the text on their screen, but the actual transaction logic happens on the server (`confirm_completion.php`), which verifies the user's balance in the database before processing."

**Q4: How would you scale this application?**
*   **Answer:**
    1.  **Database:** Add indexing to frequently searched columns (like `skill_title` or `category_id`).
    2.  **Chat:** Move from Polling to **WebSockets** (Socket.io or Ratchet PHP) for true real-time performance.
    3.  **Code:** Refactor repetitive PHP code into a Service/Repository pattern.

**Q5: What was the most challenging part?**
*   **Answer:** (Personalize this, but a good answer is:) "Implementing the asynchronous state management. Keeping the UI in sync with the backend (like updating the unread message count or credit balance without refreshing the page) required careful planning of the JSON APIs."

## 5. Deployment / Setup
*   The project includes a `README.md` with full setup instructions.
*   It requires a standard LAMP/WAMP/XAMPP stack.
*   Configuration is handled via `.env` (or config files) for security.
