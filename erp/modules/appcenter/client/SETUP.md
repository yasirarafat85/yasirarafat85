# ⚡ Silent Install Helper — সেটআপ গাইড (Windows)

ড্যাশবোর্ডের **"Install"** বাটনে ক্লিক করলে সরাসরি (silent) ইনস্টল করাতে হলে
প্রতিটি ক্লায়েন্ট পিসিতে **একবার** এই ছোট helper সেটআপ করতে হয়।
*(এটা ছাড়াও "Download" বাটন সব পিসিতেই কাজ করবে — helper শুধু one-click auto-install এর জন্য।)*

---

## কীভাবে কাজ করে

```
[ব্রাউজারে Install ক্লিক]
        │  appdeploy://5?t=টোকেন
        ▼
[Windows প্রোটোকল হ্যান্ডলার]  →  appdeploy.ps1 চালায়
        │
        ▼
[স্ক্রিপ্ট সার্ভারের api.php কল করে]  →  ইনস্টলারের নেটওয়ার্ক পাথ + silent args পায়
        │
        ▼
[নেটওয়ার্ক শেয়ার থেকে ইনস্টলার silent চালায়]  ✅
```

ব্রাউজার নিজে exe চালায় না — Windows-এ রেজিস্টার করা helper চালায়। এটাই এন্টারপ্রাইজ পদ্ধতি।

---

## ⭐ সবচেয়ে সহজ উপায় — অটো-ইনস্টলার (প্রস্তাবিত)

এই `client` ফোল্ডারের **দুটো ফাইল** (`install-helper.bat` ও `appdeploy.ps1`)
ক্লায়েন্ট পিসিতে একসাথে কপি করুন (যেকোনো ফোল্ডারে/পেনড্রাইভে)।

1. **`install-helper.bat`**-এ ডাবল-ক্লিক করুন
2. জিজ্ঞেস করলে সার্ভারের ঠিকানা দিন — যেমন `http://192.168.0.10/erp`
3. **Enter** চাপুন

ব্যস! এটি নিজে থেকেই ফোল্ডার বানায়, স্ক্রিপ্ট কপি করে, URL বসায় ও
protocol রেজিস্টার করে। **admin দরকার নেই।** ✅

**কোথায় বসে (হিডেন):** helper স্বয়ংক্রিয়ভাবে এখানে বসে —
`%LOCALAPPDATA%\AppDeploy` (অর্থাৎ `C:\Users\<নাম>\AppData\Local\AppDeploy`)।
`AppData` ফোল্ডার এমনিতেই লুকানো, তার উপর ফোল্ডারটাকে **hidden**-ও করা হয় —
তাই ইউজারের চোখে পড়ে না। 🙈

> 💡 `.bat` আর `.ps1` — দুটো ফাইল একসাথে একই ফোল্ডারে রেখে bat চালাতে হবে।

**Program Files-এ রাখতে চাইলে:** `install-helper.bat` খুলে ভেতরের
`HELPERDIR` লাইনটা `%ProgramFiles%\AppDeploy` করে দিন, আর bat-টা
**Run as administrator** দিয়ে চালান (Program Files-এ লিখতে admin লাগে)।

---

## 🔧 অথবা ম্যানুয়াল উপায় (৩ ধাপ)

### ধাপ ১ — ফাইল রাখুন
`C:\AppDeploy\` ফোল্ডার বানিয়ে তাতে **`appdeploy.ps1`** কপি করুন।

### ধাপ ২ — সার্ভার ঠিকানা দিন
`appdeploy.ps1` খুলে উপরের লাইনটা বদলান:
```powershell
$ServerUrl = "http://192.168.0.10/erp"
```

### ধাপ ৩ — প্রোটোকল রেজিস্টার করুন
`install-helper.reg` ফাইলে **ডাবল-ক্লিক → Yes**।
(ps1 অন্য ফোল্ডারে রাখলে .reg ফাইলের পাথও বদলে নিন।)

✅ ব্যস! এখন ড্যাশবোর্ডের "Install" বাটন silent ইনস্টল করবে।

---

## 🏢 অনেক পিসিতে একসাথে (Domain / GPO)
- `C:\AppDeploy\appdeploy.ps1` ও `.reg` একটা শেয়ারে রাখুন।
- **Group Policy → Logon Script** দিয়ে `.reg` ইমপোর্ট ও ps1 কপি অটো করান।
  অথবা: `reg import \\server\share\install-helper.reg` একটা logon script-এ দিন।

---

## 🔧 সমস্যা হলে
- Install-এ কিছু হচ্ছে না → এই পিসিতে helper সেটআপ নেই, অথবা `$ServerUrl` ভুল।
- লগ দেখুন: `%TEMP%\appdeploy.log` (যেমন `C:\Users\<নাম>\AppData\Local\Temp\appdeploy.log`)।
- "ফাইল পাওয়া যায়নি" → পিসি থেকে নেটওয়ার্ক শেয়ারে (`\\SERVER\Software\...`) অ্যাক্সেস আছে কিনা দেখুন।
- silent না হয়ে সাধারণ ইনস্টলার খুলছে → অ্যাডমিন প্যানেলে ওই অ্যাপের **Silent Args** ঠিক দিন
  (যেমন `.msi` হলে `/qn`, অনেক `.exe` হলে `/S` বা `/silent`)।

---

## 📦 Winget অ্যাপ প্রসঙ্গে
winget-ধরনের অ্যাপে ব্রাউজার থেকেই **৩টি কাজ** করা যায় — helper নিজেই
সঠিক winget কমান্ড চালায়:

| বাটন | winget কমান্ড |
|------|----------------|
| ⚡ **Install** | `winget install --id <ID> --silent` |
| 🔄 **Update** | `winget upgrade --id <ID> --silent` |
| 🗑️ **Uninstall** | `winget uninstall --id <ID> --silent` |

শর্ত: ক্লায়েন্ট পিসিতে **App Installer / winget** থাকতে হবে (Windows 10/11-এ
সাধারণত থাকে; না থাকলে Microsoft Store থেকে "App Installer")। winget অ্যাপে
"Download" বাটন নেই, কারণ winget নিজেই অফিশিয়াল সোর্স থেকে নামায়।
(নেটওয়ার্ক অ্যাপে শুধু Install/Download — update/uninstall winget-এর জন্য।)

## 📊 Install Log
প্রতিটি silent install-এ helper পিসির নাম (`COMPUTERNAME`) সার্ভারে পাঠায়,
আর ইনস্টল সফল/ব্যর্থ হলে সেটাও জানায়। অ্যাডমিন `Settings → Install Logs`-এ
কোন পিসি থেকে কী ইনস্টল হলো সব দেখতে পারেন।

## 🔒 নিরাপত্তা
- প্রোটোকল URL-এ শুধু অ্যাপের **id + token** থাকে, কোনো পাথ নয় — তাই কেউ ইচ্ছেমতো
  ফাইল চালাতে পারবে না। আসল পাথ শুধু সার্ভারে থাকে ও token যাচাইয়ের পরই দেওয়া হয়।
- লাইভে যাওয়ার আগে `config.php`-এ **APP_SECRET** একটি লম্বা random স্ট্রিং দিয়ে বদলান।
