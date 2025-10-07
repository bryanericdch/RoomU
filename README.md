# 🏫 Room-U: Classroom Availability and Scheduling System New rule

**Room-U** is a web-based system designed to help schools manage classroom availability, schedules, and occupancy efficiently.  
It enables **admins**, **instructors**, and **students** to track and manage rooms in real-time, minimizing scheduling conflicts and ensuring smoother classroom operations.

---

## 🚀 Project Overview

Room-U aims to:
- Resolve room conflicts and overlapping schedules.
- Allow instructors and admins to monitor room occupancy and availability in real time.
- Automate room status updates based on class schedules.
- Provide an easy-to-use dashboard for tracking building and department statistics.

---

## 👥 User Roles & Features

### 🛠 Admin
- Manage buildings, rooms, departments, and instructors.
- Pre-create instructor accounts.
- Set room availability (Available, Occupied, Maintenance).
- View dashboards showing building statistics and current room statuses.

### 👨‍🏫 Instructor
- Create, edit, and remove courses, subjects, and sections.
- View and track room availability.
- Check-in to a room for a scheduled class (automatically checks out when the class ends).
- View their own active and past check-ins.

### 🎓 Student
- View real-time room availability and statuses (read-only).
- Authenticate to access the system securely.

---

## 🕒 Real-Time Features

- **Automatic Check-Out:** When the current time meets the class’s scheduled end time, the system auto-checks out the room and marks it as available again.
- **5-Minute Grace Period:** A built-in allowance ensures minor schedule deviations don’t cause conflicts.
- **Real-Time Clock Integration:** The system syncs with the current time (based on `Asia/Manila` timezone).

---

## 🧩 Tech Stack

| Layer | Tools / Technologies |
|-------|----------------------|
| Frontend | HTML, CSS (Tailwind CSS), JavaScript |
| Backend | PHP (Procedural or OOP) |
| Database | MySQL (via XAMPP) |
| Version Control | Git & GitHub |
| Server | Apache (XAMPP) |

---

## 🗂️ Folder Structure

