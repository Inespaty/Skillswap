# SkillSwap - Exchange Skills, Grow Together 

**SkillSwap** is a community-driven platform designed to make learning accessible by removing financial barriers. We believe everyone has something to teach and something to learn.

---

##  The Problem We Solve

In a world where specialized education and skill-building often come with a high price tag, many talented individuals are locked out of growth opportunities. **SkillSwap** solves this by:
- **Democratizing Learning:** Eliminating the need for money in education.
- **Valuing Time:** Treating one hour of teaching as a universal currency.
- **Building trust:** Creating a verified community of mentors and learners.

---

##  Implementation & Architecture

SkillSwap is built as a **Multi-Page Application (MPA)** with a custom-built modular PHP backend.

### 1. Secure Backend Framework
Instead of a heavy framework, we implemented a lightweight, security-first core in `backend/init.php`. This includes:
- **Centralized Auth:** Session-based authentication with `requireAuth()` and `requireAdmin()` guards.
- **Security Headers:** Protection against XSS, Clickjacking, and Sniffing.
- **Rate Limiting:** Global and auth-specific limits to prevent brute-force attacks.
- **CSRF Protection:** Secure token validation for all state-changing requests.

### 2. Time-Banking Credit System
The heart of SkillSwap is an **atomic transaction system**. When a session is completed:
- Credits are transferred between users within a **SQL Transaction** to ensure data integrity.
- Every exchange is logged in `Transactions` and `Admin_Logs` for auditability.
- Real-time balance updates are handled via PHP-based API endpoints.

### 3. Frontend Component Architecture
We use a hybrid approach of traditional HTML and modular JavaScript:
- **Reusable Parts:** Headers, footers, and navbars are loaded dynamically from `assets/components/`.
- **Vanilla JS Workflows:** Custom scripts handle complex state like skill filtering and request management without the overhead of a heavy SPA framework.
- **Responsive Design:** A custom design system built on top of **Bootstrap 5**, ensuring a premium look and feel.

---

##  Tech Stack

### Backend
- **Core:** PHP 7.4+
- **Database:** MySQL (MariaDB compatible)
- **Persistence:** PDO (PHP Data Objects) with Prepared Statements
- **Environment:** Manual `.env` configuration for portability

### Frontend
- **Structure:** HTML5 Semantic Markup
- **Styling:** CSS3, Bootstrap 5, FontAwesome 6
- **Logic:** Vanilla JavaScript (ES6+), Fetch API
- **Typography:** Inter (Google Fonts)

---

##  Project Structure

- **/assets**: Design tokens, custom CSS, and modular JS components.
- **/backend**: Core logic, API endpoints, and database connection helpers.
- **/database**: SQL schema and migration files.
- **/scripts**: Server-side utility scripts for maintenance and admin setup.
- **/uploads**: Secure storage for user-generated content.

---

##  Getting Started

### Prerequisites
- PHP 7.4 or higher
- MySQL / MariaDB
- Web Server (Apache/Nginx) or PHP's built-in server

### Installation
1.  **Clone & Navigate:**
    ```bash
    git clone https://github.com/your-repo/skillswap.git
    cd skillswap
    ```
2.  **Database Setup:**
    Create a database named `skillswap` and import `database/schema.sql`.
3.  **Configuration:**
    ```bash
    cp .env.example .env
    # Edit .env with your DB credentials
    ```
4.  **Run Server:**
    ```bash
    php -S localhost:8080 -t .
    ```

---

