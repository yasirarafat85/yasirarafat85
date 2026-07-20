# 🖥️ লোকাল পিসিতে চালানোর সম্পূর্ণ গাইড (Windows)

শূন্য থেকে শুরু করে ধাপে ধাপে — ডাটাবেস থেকে লগইন পর্যন্ত।

---

## 📦 ধাপ ১ — XAMPP ইনস্টল করুন (PHP + MySQL একসাথে)

আলাদা করে PHP/MySQL লাগবে না — XAMPP-এ সব আছে।

1. যান 👉 **https://www.apachefriends.org** → Windows-এর জন্য ডাউনলোড
2. ইনস্টল করুন (ডিফল্ট সেটিংসেই OK) → সাধারণত `C:\xampp`-এ বসবে

---

## 🚀 ধাপ ২ — Apache ও MySQL চালু করুন

1. **XAMPP Control Panel** খুলুন
2. **Apache** → **Start**
3. **MySQL** → **Start**

দুটোই **সবুজ** হলে চালু হয়েছে ✅

> ⚠️ Apache চালু না হলে সাধারণত Skype/অন্য কিছু 80 পোর্ট দখল করে রাখে — বন্ধ করুন,
> অথবা XAMPP-এ Apache পোর্ট 8080 করে নিন (তখন লিংক হবে `http://localhost:8080/erp/`)।

---

## 📁 ধাপ ৩ — কোড পিসিতে আনুন

কোড GitHub-এর `claude/project-ideas-av2z5u` ব্রাঞ্চে আছে। যেকোনো একটা উপায়:

**সহজ (ZIP):**
1. GitHub রিপোতে → branch **`claude/project-ideas-av2z5u`** সিলেক্ট
2. **Code** → **Download ZIP**
3. আনজিপ করে ভেতরের **`erp`** ফোল্ডার কপি করুন
4. রাখুন 👉 **`C:\xampp\htdocs\erp`**

**অথবা Git:**
```bash
cd C:\xampp\htdocs
git clone -b claude/project-ideas-av2z5u <আপনার-রিপো-লিংক> temp
```
তারপর `temp\erp` ফোল্ডারটা `htdocs`-এ সরান।

✅ শেষ পথ: `C:\xampp\htdocs\erp\`

---

## 🗄️ ধাপ ৪ — ডাটাবেস সেটিং

`C:\xampp\htdocs\erp\config\config.php` Notepad-এ খুলুন।
XAMPP-এ সাধারণত এটাই ঠিক থাকে:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'erp_foundation');
define('DB_USER', 'root');
define('DB_PASS', '');        // XAMPP-এ খালি — এটাই রাখুন
```

> 💡 আলাদা করে ডাটাবেস বানাতে হবে **না** — installer নিজেই বানাবে।

---

## ⚙️ ধাপ ৫ — এক-ক্লিক ইনস্টল

ব্রাউজারে যান 👉 `http://localhost/erp/install.php`

এটি নিজে থেকেই বানাবে: ডাটাবেস + টেবিল, Role/Permission/Menu,
Super Admin অ্যাকাউন্ট, App Center-এ ৬টি নমুনা অ্যাপ।

**"✅ ইনস্টলেশন সম্পন্ন"** দেখলেই শেষ।

> 🔒 এরপর **`install.php` ফাইলটা ডিলিট করুন**।

---

## 🔑 ধাপ ৬ — লগইন

`http://localhost/erp/`

| | |
|---|---|
| 📧 ইমেইল | `admin@erp.local` |
| 🔑 পাসওয়ার্ড | `admin123` |

> প্রথম লগইনের পর পাসওয়ার্ড বদলে নিন (Users পেজ থেকে)।

---

## 🌐 ধাপ ৭ — App Center (নেটওয়ার্ক শেয়ার) — ঐচ্ছিক

1. একটা নেটওয়ার্ক ফোল্ডার শেয়ার করুন (যেমন `\\FILESERVER\Software`), তাতে ইনস্টলার রাখুন
2. `config.php`-এ:
   ```php
   define('NETWORK_SHARE_BASE', '\\\\FILESERVER\\Software');
   define('APP_SECRET', 'একটি-লম্বা-random-গোপন-স্ট্রিং');
   ```
3. অ্যাপে **Settings → App Manager** → নিজের অ্যাপ/পাথ যোগ করুন

---

## ❓ অন্য পিসি থেকে ব্রাউজারে ঢুকে কি এক ক্লিকে ইনস্টল হবে?

এই অ্যাপটা একটা পিসিতে (সার্ভার) চলবে, বাকিরা নেটওয়ার্কে ঐ পিসির
IP দিয়ে ঢুকবে — যেমন `http://192.168.0.10/erp/`
(সার্ভার পিসির IP জানতে: cmd-এ `ipconfig` → IPv4 Address)।

**গুরুত্বপূর্ণ সত্য:** 🔒 কোনো ব্রাউজার নিরাপত্তার কারণে নিজে থেকে `.exe`
চালাতে পারে না। তাই "সত্যিকার এক ক্লিক" পেতে **প্রতিটি ক্লায়েন্ট পিসিতে
একবার** ছোট helper বসাতে হয়।

| ক্লায়েন্ট পিসিতে যা করা আছে | ⚡ Install বাটন | ⬇️ Download বাটন |
|------------------------------|-----------------|-------------------|
| কিছুই সেটআপ নেই | ❌ কাজ করবে না | ✅ নামবে → নিজে ডাবল-ক্লিক করে চালাতে হবে |
| helper সেটআপ করা (একবার) | ✅ **সত্যিকার এক ক্লিক silent** | ✅ চলবে |

**helper সেটআপ (প্রতি পিসিতে একবার, ~২ মিনিট):**
1. `C:\AppDeploy\` ফোল্ডার বানিয়ে তাতে `modules/appcenter/client/appdeploy.ps1` কপি করুন
2. `appdeploy.ps1`-এ `$ServerUrl` আপনার সার্ভার IP দিন (যেমন `http://192.168.0.10/erp`)
3. `install-helper.reg`-এ ডাবল-ক্লিক → Yes

বিস্তারিত: `modules/appcenter/client/SETUP.md`।
🏢 অনেক পিসি হলে **Group Policy (GPO)** দিয়ে একসাথে সব পিসিতে বসানো যায়।

**আরও ২ শর্ত (এক ক্লিক কাজ করতে):**
- ঐ পিসি থেকে নেটওয়ার্ক শেয়ারে (`\\FILESERVER\Software`) অ্যাক্সেস থাকতে হবে
- অ্যাপের সঠিক **Silent Args** দেওয়া থাকতে হবে (`.msi` = `/qn`, অনেক `.exe` = `/S` বা `/silent`)

---

## ✅ সংক্ষেপে

```
১. XAMPP ইনস্টল
২. Apache + MySQL চালু (সবুজ)
৩. erp ফোল্ডার → C:\xampp\htdocs\erp
৪. config.php চেক (XAMPP-এ এমনিতেই ঠিক)
৫. http://localhost/erp/install.php  চালান
৬. http://localhost/erp/  → admin@erp.local / admin123

অন্য পিসি থেকে:  http://<সার্ভার-IP>/erp/
সত্যিকার এক-ক্লিক ইনস্টল চাইলে: ঐ পিসিতে একবার helper সেটআপ (client/SETUP.md)
```

---

## 🔧 সাধারণ সমস্যা

| সমস্যা | সমাধান |
|--------|--------|
| `install.php`-এ DB error | MySQL চালু আছে কিনা দেখুন; `config.php`-এর DB তথ্য মিলিয়ে নিন |
| পেজ সাদা/blank | `config.php`-এ `APP_DEBUG` `true` করে error দেখুন |
| অন্য পিসি থেকে খুলছে না | Firewall-এ Apache (80/8080) allow করুন; সঠিক IP দিন |
| Install বাটনে কিছু হয় না | ঐ পিসিতে helper সেটআপ নেই — `client/SETUP.md` দেখুন |
| Download-এ "ফাইল পাওয়া যায়নি" | সার্ভার পিসির নেটওয়ার্ক শেয়ারে অ্যাক্সেস/পাথ ঠিক আছে কিনা দেখুন |
