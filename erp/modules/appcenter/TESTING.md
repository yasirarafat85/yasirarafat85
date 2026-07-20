# ✅ App Center — টেস্টিং চেকলিস্ট (ধাপে ধাপে)

একটা টেস্ট পিসিতে এই ধাপগুলো ক্রমে করুন। প্রতিটি ধাপে **প্রত্যাশিত ফল**
আর **আটকালে কোথায় দেখবেন** দেওয়া আছে। সহজ থেকে কঠিনের দিকে সাজানো।

> 💡 প্রথম ৩টি ধাপ (সার্ভার + ডাউনলোড) যেকোনো পিসিতেই চলে। silent install
> ও elevation-এর ধাপগুলো Windows পিসিতে করতে হবে।

---

## 🟢 Phase 0 — সার্ভার চালু

- [ ] XAMPP-এ **Apache** ও **MySQL** সবুজ (চালু)
- [ ] `http://localhost/erp/install.php` খুলে **"ইনস্টলেশন সম্পন্ন"** দেখা গেছে
- [ ] এরপর `install.php` ডিলিট করা হয়েছে
- [ ] `http://localhost/erp/` → `admin@erp.local` / `admin123` দিয়ে লগইন হয়েছে

**আটকালে:** DB error হলে MySQL চালু আছে কিনা ও `config.php`-এর DB তথ্য দেখুন।
সাদা পেজ হলে `config.php`-এ `APP_DEBUG` `true` রেখে error পড়ুন।

---

## 🟢 Phase 1 — App Center বেসিক

- [ ] বাম মেনুতে **App Center** ক্লিক → ৬টি নমুনা অ্যাপ কার্ড দেখা যাচ্ছে
- [ ] উপরে সার্চ বক্সে "chrome" টাইপ → শুধু Chrome দেখাচ্ছে
- [ ] ক্যাটাগরি ড্রপডাউনে "Browser" → শুধু ব্রাউজার অ্যাপ
- [ ] **Settings → App Manager** → নতুন অ্যাপ যোগ করে দেখুন (network ও winget দুই ধরনেই)
- [ ] যোগ করা অ্যাপ ফ্রন্টএন্ডে দেখা যাচ্ছে

**প্রত্যাশা:** winget অ্যাপে **Install/Update/Uninstall** বাটন; network অ্যাপে
**Install/Download**।

---

## 🟢 Phase 2 — Download (helper ছাড়াই চলে)

- [ ] একটা **network** অ্যাপের নেটওয়ার্ক পাথ আসল একটা ফাইলের দিকে সেট করুন
      (টেস্টে সহজ করতে `config.php`-এ `NETWORK_SHARE_BASE` একটা লোকাল ফোল্ডারও দিতে পারেন)
- [ ] ফ্রন্টএন্ডে ঐ অ্যাপের **⬇️ Download** ক্লিক → ফাইল নামছে
- [ ] **Settings → Install Logs** → ঐ ডাউনলোড **success** হিসেবে লগ হয়েছে (ইউজার + IP সহ)

**আটকালে:** "ফাইল পাওয়া যায়নি" এলে সার্ভার পিসি থেকে ঐ পাথে ফাইল আছে কিনা
ও শেয়ারে অ্যাক্সেস আছে কিনা দেখুন। লগে **failed** + "file not found" থাকবে।

---

## 🟡 Phase 3 — Simple helper (silent install, admin/টেস্ট পিসি)

*(এই ধাপ একটা পিসিতে যেখানে আপনি admin — বা winget user-scope চলে)*

- [ ] `client/` থেকে **`install-helper.bat` + `appdeploy-worker.ps1`** টেস্ট পিসিতে কপি
- [ ] `install-helper.bat`-এ ডাবল-ক্লিক → সার্ভার URL দিন (যেমন `http://<সার্ভার-IP>/erp`)
- [ ] "[OK] Helper setup COMPLETE" দেখা গেছে
- [ ] `%LOCALAPPDATA%\AppDeploy\`-এ `appdeploy.ps1`, `appdeploy-worker.ps1`, `config.json` আছে
- [ ] ব্রাউজারে একটা **winget** অ্যাপের **⚡ Install** ক্লিক
- [ ] ব্রাউজার protocol খোলার অনুমতি চাইলে **Allow/Open** দিন
- [ ] কিছুক্ষণ পর অ্যাপটা আসলেই ইনস্টল হয়েছে (Start মেনুতে খুঁজুন)
- [ ] **Install Logs**-এ **install / success** + পিসির নাম দেখা যাচ্ছে

**আটকালে দেখুন এই লগ ফাইল:**
- `%TEMP%\appdeploy.log` → dispatcher কী করল
- `%LOCALAPPDATA%\AppDeploy\worker.log` → worker কী করল
- Install Logs-এ **failed** হলে note-এ কারণ থাকবে (যেমন winget exit code)

**সাধারণ কারণ:** Install-এ কিছু হয় না → protocol register হয়নি বা bat চলেনি।
winget না পাওয়া → পিসিতে "App Installer" নেই (Microsoft Store থেকে দিন)।

---

## 🟡 Phase 4 — Update ও Uninstall (winget)

- [ ] একই winget অ্যাপে **🔄 Update** ক্লিক → নতুন ভার্সন থাকলে আপডেট হয়
- [ ] **🗑️ Uninstall** ক্লিক → confirm চায় → Yes → অ্যাপ মুছে যায়
- [ ] Install Logs-এ **update** ও **uninstall** আলাদা ব্যাজে দেখা যাচ্ছে
- [ ] একটা **network** অ্যাপে Update/Uninstall বাটন **নেই** (শুধু winget-এ) — ঠিক আছে কিনা দেখুন

**আটকালে:** uninstall না হলে winget ID সঠিক কিনা দেখুন
(cmd-এ `winget list` দিয়ে আসল ID মিলিয়ে নিন)।

---

## 🔴 Phase 5 — স্ট্যান্ডার্ড ইউজার পিসি (admin ছাড়া install)

*(এটাই আসল লক্ষ্য — এমন পিসি যেখানে লগইন করা ইউজার admin নয়)*

**সেটআপ (admin একবার — PowerShell "Run as administrator"):**

- [ ] `client/` থেকে ৩টি ফাইল টেস্ট পিসিতে নিন: `appdeploy.ps1`, `appdeploy-worker.ps1`, `setup-elevation.ps1`
- [ ] SYSTEM মোডে চালান:
      `\.\setup-elevation.ps1 -Server "http://<সার্ভার-IP>/erp" -RunAs system`
- [ ] "Elevation setup সম্পন্ন" দেখা গেছে
- [ ] `Task Scheduler`-এ **AppDeployRunner** টাস্ক তৈরি হয়েছে (SYSTEM হিসেবে)
- [ ] `C:\ProgramData\AppDeploy\`-এ স্ক্রিপ্ট + `config.json` আছে

**এখন স্ট্যান্ডার্ড ইউজার হিসেবে লগইন করে:**

- [ ] ব্রাউজারে একটা winget অ্যাপের **⚡ Install** ক্লিক
- [ ] **UAC/পাসওয়ার্ড চায়নি** — তবু কিছুক্ষণ পর অ্যাপ ইনস্টল হয়েছে ✅
- [ ] Install Logs-এ **success** + পিসির নাম দেখা যাচ্ছে
- [ ] Uninstall-ও admin ছাড়া কাজ করছে

**admin-অ্যাকাউন্ট মোডও টেস্ট করতে চাইলে:**
- [ ] অন্য পিসিতে `-RunAs admin` দিয়ে সেটআপ (admin ইউজার/পাসওয়ার্ড চাইবে)
- [ ] স্ট্যান্ডার্ড ইউজারে Install → একইভাবে কাজ করে

**আটকালে দেখুন:**
- `C:\ProgramData\AppDeploy\worker.log` → টাস্ক আসলে চলেছে কিনা ও কী হলো
- `%TEMP%\appdeploy.log` → dispatcher টাস্ক trigger করতে পারল কিনা
- টাস্ক run হয় না → স্ট্যান্ডার্ড ইউজারের run-অনুমতি নেই।
  Task Scheduler → AppDeployRunner → (সেটআপ স্ক্রিপ্ট এটা অটো করে, না হলে)
  ম্যানুয়ালি **Users** গ্রুপকে read+execute অনুমতি দিন।
- queue ফাইল জমে থাকছে কিন্তু চলছে না → টাস্ক trigger হচ্ছে না;
  `schtasks /run /tn AppDeployRunner` cmd-এ চালিয়ে টেস্ট করুন।

---

## 📋 দ্রুত ডিবাগ রেফারেন্স

| কোথায় | কী দেখায় |
|--------|----------|
| `%TEMP%\appdeploy.log` | dispatcher (অনুরোধ queue + routing) |
| `%ProgramData%\AppDeploy\worker.log` | worker (আসল install, elevated) |
| **Settings → Install Logs** | সার্ভারে সব ইভেন্ট (পিসি, স্ট্যাটাস, কারণ) |
| `Task Scheduler → AppDeployRunner` | রানার টাস্ক ঠিকঠাক আছে কিনা |
| cmd: `winget list` | আসল winget ID মেলানো |

---

## ✅ সব ঠিক থাকলে

আপনি এখন ব্রাউজার থেকে — **যেকোনো নেটওয়ার্ক পিসিতে, স্ট্যান্ডার্ড ইউজারেও,
admin ছাড়া** — সফটওয়্যার **Install / Update / Uninstall / Download** করতে
পারছেন, আর সব **Install Logs**-এ রেকর্ড হচ্ছে। 🎉
