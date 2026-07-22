# ✍️ কনটেন্ট রাইটিং একাডেমি

কনটেন্ট রাইটিং শেখা, অনুশীলন ও AI সহকারী — সব এক জায়গায়। **Native PHP + SQLite** দিয়ে তৈরি, XAMPP-এ সরাসরি চলে।

---

## 🎯 কী কী করা যায়

অ্যাপটি তিনটি লেভেলে কাজ করে:

| লেভেল | কী করে | API লাগে? |
|-------|--------|-----------|
| **১ — শেখা** | ৪টি ক্যাটাগরিতে টিপস, উদাহরণ ও প্র্যাকটিস চ্যালেঞ্জ | না |
| **২ — খসড়া বানাও** | টেমপ্লেটে ঘর পূরণ করে খসড়া তৈরি (PAS, AIDA, হুক-গল্প...) | না |
| **৩ — AI সহকারী** | লেখা যাচাই (ফিডব্যাক + স্কোর) ও নতুন লেখা তৈরি | হ্যাঁ (key) |

**৪টি ক্যাটাগরি:** ফেসবুক পোস্ট · সেলস কপি · ব্লগ/আর্টিকেল · গল্প/ব্যক্তিগত অভিজ্ঞতা

**অন্যান্য ফিচার:** লগইন/রেজিস্টার · লেখা সেভ/এডিট/ডিলিট · সার্চ ও ফিল্টার · শব্দ সংখ্যা ও পড়ার সময় · সেল্‌ফ-চেক চেকলিস্ট · .txt এক্সপোর্ট · ড্যাশবোর্ড পরিসংখ্যান।

---

## 🖥️ প্রয়োজন

- **XAMPP** (PHP 8.0 বা তার বেশি) — [apachefriends.org](https://www.apachefriends.org) থেকে ডাউনলোড
- ইন্টারনেট সংযোগ (শুধু লেভেল ৩ / AI ফিচারের জন্য)

> XAMPP-এ `pdo_sqlite` ও `curl` এক্সটেনশন সাধারণত ডিফল্টভাবে চালু থাকে — আলাদা কিছু ইনস্টল করতে হয় না।

---

## 🚀 চালু করার ধাপ (XAMPP)

**১.** XAMPP ইনস্টল করুন এবং **Apache** চালু করুন (XAMPP Control Panel → Apache → Start)।

**২.** এই `content-writing-app` ফোল্ডারটি XAMPP-এর `htdocs` ফোল্ডারে রাখুন। সাধারণত পথ হয়:
- Windows: `C:\xampp\htdocs\content-writing-app`
- macOS: `/Applications/XAMPP/htdocs/content-writing-app`
- Linux: `/opt/lampp/htdocs/content-writing-app`

**৩.** ব্রাউজারে যান:
```
http://localhost/content-writing-app/
```

**৪.** প্রথমবার খুললেই ডেটাবেস (`data/app.sqlite`) **নিজে থেকে তৈরি হবে** এবং ৪টি ক্যাটাগরি ও টেমপ্লেট বসে যাবে। আলাদা কোনো ডেটাবেস সেটআপ লাগবে না।

**৫.** একটি অ্যাকাউন্ট **রেজিস্টার** করুন — ব্যস, শুরু!

---

## 🤖 AI (লেভেল ৩) চালু করা — ঐচ্ছিক

AI ছাড়াও লেভেল ১ ও ২ পুরোপুরি চলবে। AI চাইলে:

**১.** লগইন করে **সেটিংস** পেজে যান।

**২.** একটি প্রোভাইডার বেছে নিন ও তার **API key** দিন:

| প্রোভাইডার | key কোথায় পাবেন |
|-----------|------------------|
| OpenRouter | https://openrouter.ai/keys |
| OpenAI | https://platform.openai.com/api-keys |
| Anthropic (Claude) | https://console.anthropic.com |

**৩.** একটি **ডিফল্ট মডেল** বেছে **সংরক্ষণ** করুন, তারপর **কানেকশন টেস্ট** চাপুন।

**৪.** এবার যেকোনো লেখায় **"AI যাচাই করো"** এবং মেনুতে **"AI লিখে দাও"** ব্যবহার করতে পারবেন।

> 🔒 আপনার API key এনক্রিপ্ট করে সংরক্ষণ করা হয় এবং UI-তে কখনো পুরোটা দেখানো হয় না।

---

## 📁 ফোল্ডার কাঠামো

```
content-writing-app/
├── config/config.php        # সেটিংস, ডিফল্ট প্রোভাইডার/মডেল
├── includes/
│   ├── db.php               # SQLite কানেকশন + টেবিল auto-create + seed
│   ├── auth.php             # সেশন, লগইন গার্ড
│   ├── functions.php        # হেল্পার (escape, CSRF, word count, key crypto)
│   └── ai_client.php        # কেন্দ্রীয় মাল্টি-প্রোভাইডার AI ক্লায়েন্ট
├── providers/               # প্রতিটি AI প্রোভাইডার আলাদা (extensible)
│   ├── openrouter.php
│   ├── openai.php
│   └── anthropic.php
├── partials/                # header (navbar) ও footer
├── auth/                    # register, login, logout
├── assets/css/style.css
├── data/                    # SQLite ডেটাবেস এখানে তৈরি হয় (গিটে যায় না)
├── index.php                # ড্যাশবোর্ড
├── categories.php, learn.php # লেভেল ১
├── generate.php             # লেভেল ২
├── ai_review.php, ai_write.php, settings.php  # লেভেল ৩
├── writings.php, writing_edit.php, writing_view.php, export.php
└── README.md
```

---

## ➕ নতুন AI প্রোভাইডার যোগ করা

কোড এমনভাবে সাজানো যে নতুন প্রোভাইডার যোগ করা সহজ:

1. `providers/` এ নতুন ফাইল বানান (যেমন `gemini.php`) — একটি ফাংশন `provider_gemini($apiKey, $model, $system, $userPrompt)` যা `['ok','text','error']` রিটার্ন করবে।
2. `config/config.php`-এ `$AI_PROVIDERS`-এ প্রোভাইডার ও মডেল যোগ করুন।
3. `includes/ai_client.php`-এর `$map`-এ একটি লাইন যোগ করুন।

বাকি অ্যাপের কিছুই বদলাতে হবে না।

---

## 🔐 নিরাপত্তা

- সব ডেটাবেস কুয়েরিতে **PDO prepared statements** (SQL injection সুরক্ষা)
- সব ফর্মে **CSRF token**
- সব আউটপুটে **htmlspecialchars** (XSS সুরক্ষা)
- পাসওয়ার্ড **password_hash** দিয়ে সংরক্ষিত
- একজন ইউজার শুধু **নিজের লেখা** এডিট/ডিলিট করতে পারে (ownership check)
- API key **এনক্রিপ্ট** করে রাখা হয়

> লাইভ ব্যবহারের আগে `config/config.php`-এ `APP_SECRET` নিজের একটি গোপন মান দিয়ে বদলে নিন।

---

## 💚 টিপ

শুধু AI দিয়ে লিখলে শেখা হয় না। আগে **নিজে লিখুন** (লেভেল ১+২), তারপর **AI-র ফিডব্যাক** (লেভেল ৩) দিয়ে ঠিক করুন — এভাবেই দ্রুত ভালো লেখক হয়ে উঠবেন।
