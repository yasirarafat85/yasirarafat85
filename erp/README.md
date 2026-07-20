# 🏢 IT Admin ERP — Foundation

একটি **মডুলার ERP বেস অ্যাপ্লিকেশন** (PHP + MySQL + Bootstrap Icons)।
এর উপর আপনি সহজে **নতুন নতুন প্রজেক্ট (module)** যোগ করতে পারবেন — User,
Menu ও Access Control সব রেডি থাকে, আর ডাটাবেস থেকে তথ্য নেওয়ার জন্য একটা
পুনঃব্যবহারযোগ্য লেয়ার দেওয়া আছে।

---

## ✨ কী কী আছে

| ফিচার | বিবরণ |
|-------|-------|
| 🔐 **Authentication** | নিরাপদ লগইন (password hashed), সেশন, CSRF সুরক্ষা |
| 👤 **User Management** | ইউজার যোগ/এডিট/ডিলিট, সক্রিয়/নিষ্ক্রিয় |
| 🎭 **Role & Permission (RBAC)** | Role বানান, প্রতিটি role-কে permission দিন — কে কী করতে পারবে |
| 📂 **Dynamic Menu** | সাইডবারের মেনু ডাটাবেস থেকে আসে, permission অনুযায়ী দেখায় |
| 🧩 **Module System** | নতুন প্রজেক্ট = নতুন module ফোল্ডার, plug & play |
| ⚙️ **Reusable DB Layer** | যেকোনো module এক লাইনে DB থেকে ডেটা নিতে পারে |
| 🌙 **Dark / Light Mode** | প্রফেশনাল Filament-স্টাইল থিম |

---

## 🚀 সেটআপ (৩ ধাপ)

**১. ফাইল রাখুন**
`erp/` ফোল্ডারটি আপনার সার্ভারে রাখুন (XAMPP হলে `htdocs/erp`)।

**২. কনফিগ করুন**
`config/config.php` খুলে ডাটাবেসের তথ্য দিন:
```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'erp_foundation');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP-এ সাধারণত খালি
```

**৩. ইনস্টল করুন**
ব্রাউজারে খুলুন: `http://localhost/erp/install.php`
এটি টেবিল, ডিফল্ট role/permission/menu ও একটি অ্যাডমিন অ্যাকাউন্ট বানাবে।

> ✅ ইনস্টলের পর **`install.php` মুছে ফেলুন**।

**ডিফল্ট লগইন:**
- 📧 `admin@erp.local`
- 🔑 `admin123`  *(প্রথম লগইনের পর পরিবর্তন করুন)*

---

## 🧩 নতুন Module (প্রজেক্ট) যোগ করা — ৪ ধাপ

ধরুন আপনি **"Support Ticket"** নামে নতুন একটা প্রজেক্ট বানাতে চান।

### ধাপ ১ — ফোল্ডার বানান
`modules/inventory/` কপি করে `modules/tickets/` বানান, `index.php` এডিট করুন।
প্রতিটি module ফাইলের শুরুতে থাকবে:
```php
require_once __DIR__ . '/../../bootstrap.php';   // সব লোড হয়
Auth::requirePermission('tickets.view');         // অ্যাক্সেস নিয়ন্ত্রণ
$db = Database::getInstance();                    // DB লেয়ার
```

### ধাপ ২ — ডাটাবেস ব্যবহার করুন
```php
// টেবিল (একবার) তৈরি
$db->query("CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(200), status VARCHAR(20) DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

// ডেটা নেওয়া / যোগ করা
$rows = $db->fetchAll("SELECT * FROM tickets WHERE status = ?", ['open']);
$id   = $db->insert('tickets', ['subject' => 'প্রিন্টার সমস্যা']);
$db->update('tickets', ['status' => 'closed'], 'id = ?', [$id]);
$db->delete('tickets', 'id = ?', [$id]);
```

### ধাপ ৩ — Permission যোগ করুন
`Settings → Roles` পেজে গিয়ে নতুন permission... অথবা phpMyAdmin-এ
`permissions` টেবিলে row যোগ করুন (যেমন `tickets.view`, `tickets.create`),
তারপর Role-কে সেই permission দিন।

### ধাপ ৪ — মেনুতে যোগ করুন
`Settings → Menu Manager` পেজে "নতুন মেনু" দিয়ে:
- টাইটেল: `Support Ticket`
- URL: `modules/tickets/index.php`
- আইকন: `bi-ticket-perforated`
- Permission: `tickets.view`

✅ ব্যস! মেনুতে চলে আসবে এবং শুধু অনুমতিপ্রাপ্ত ইউজাররাই দেখবে।

---

## 📁 ফোল্ডার কাঠামো

```
erp/
├── config/config.php        # ডাটাবেস ও অ্যাপ সেটিংস
├── core/
│   ├── Database.php          # পুনঃব্যবহারযোগ্য DB লেয়ার (PDO)
│   ├── Auth.php              # লগইন + RBAC
│   └── helpers.php           # e(), url(), csrf, flash ইত্যাদি
├── includes/
│   ├── header.php            # সাইডবার + টপবার (ডাইনামিক মেনু)
│   ├── footer.php            # স্ক্রিপ্ট (theme, modal)
│   └── 403.php               # অনুমতি নেই পেজ
├── auth/login.php, logout.php
├── database/schema.sql       # টেবিল স্ট্রাকচার
├── install.php               # এক-ক্লিক ইনস্টলার
├── bootstrap.php             # সব লোড করে (প্রতি পেজে include)
├── index.php                 # ড্যাশবোর্ড
├── users.php  roles.php  menus.php   # কোর ব্যবস্থাপনা
└── modules/
    └── inventory/index.php   # উদাহরণ module (কপি করে নতুন বানান)
```

---

## 🔒 নিরাপত্তা টিপস
- লাইভে যাওয়ার আগে `config/config.php`-এ `APP_DEBUG` → `false` করুন।
- ইনস্টলের পর `install.php` মুছে ফেলুন।
- ডিফল্ট অ্যাডমিন পাসওয়ার্ড বদলান।
- আউটপুটে সবসময় `e()` ব্যবহার করুন (XSS ঠেকাতে)।
- ফর্মে সবসময় `csrf_field()` দিন।

---

## 🧱 প্রযুক্তি
PHP 8+ · MySQL/MariaDB · PDO · Bootstrap Icons · কোনো ভারী framework নেই — সহজে বোঝা ও শেখা যায়।
