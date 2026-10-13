<!DOCTYPE html>  
<html lang="bn">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">  
    <title>আমাদের লক্ষ্য ও উদ্দেশ্য | INFINITY HQ</title>  
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;600;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">  
    <style>  
    :root {  
        --primary: #4e5cf5;  
        --primary-dark: #2f39c9;  
        --primary-glow: rgba(78, 92, 245, 0.4);  
        --accent: #00f2ff;  
        --bg: #030305;  
        --card-bg: #0d0d14;  
        --text: #e0e0e0;  
        --border: rgba(255, 255, 255, 0.08);  
        --neon-pink: #e91e63;  
    }  

    * { margin: 0; padding: 0; box-sizing: border-box; }  

    html { overflow-x: hidden; width: 100%; }  

    body {  
        font-family: 'Hind Siliguri', sans-serif;  
        background-color: var(--bg);  
        color: var(--text);  
        line-height: 1.8;  
        overflow-x: hidden;  
        width: 100%;  
        word-wrap: break-word;  
    }  

    img, svg { max-width: 100%; display: block; }  

    /* Animated Tech Background */  
    body::before {  
        content: "";  
        position: fixed;  
        top: 0; left: 0; width: 100%; height: 100%;  
        background:  
            radial-gradient(circle at 20% 30%, rgba(78, 92, 245, 0.08) 0%, transparent 40%),  
            radial-gradient(circle at 80% 70%, rgba(0, 242, 255, 0.05) 0%, transparent 40%);  
        z-index: -1;  
    }  

    .container {  
        max-width: 1100px;  
        margin: 0 auto;  
        padding: 20px;  
        width: 100%;  
    }  

    /* --- Modern Hero Section --- */  
    .hero-section {  
        text-align: center;  
        padding: 100px 20px;  
        background: linear-gradient(180deg, rgba(78, 92, 245, 0.15) 0%, transparent 100%);  
        border-bottom: 1px solid var(--border);  
        margin-bottom: 60px;  
        position: relative;  
        width: 100%;  
    }  

    .hero-section h1 {  
        font-weight: 700;  
        font-size: clamp(1.8rem, 6vw, 3.5rem);  
        color: #fff;  
        margin-bottom: 15px;  
        text-shadow: 0 0 20px rgba(255,255,255,0.2);  
        line-height: 1.3;  
    }  

    .hero-section p {  
        color: var(--accent);  
        font-family: 'Orbitron', sans-serif;  
        font-size: clamp(0.85rem, 3vw, 1.4rem);  
        letter-spacing: clamp(1px, 1vw, 5px);  
        text-transform: uppercase;  
        font-weight: 900;  
    }  

    /* --- Premium Card Design --- */  
    .glass-card {  
        background: var(--card-bg);  
        border: 1px solid var(--border);  
        border-radius: 30px;  
        padding: 45px;  
        margin-bottom: 40px;  
        position: relative;  
        box-shadow: 0 20px 40px rgba(0,0,0,0.4);  
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);  
        overflow: hidden;  
    }  

    .glass-card:hover {  
        border-color: var(--primary);  
        transform: translateY(-5px);  
        box-shadow: 0 0 40px rgba(78, 92, 245, 0.15);  
    }  

    .glass-card h2 {  
        font-size: clamp(1.3rem, 4vw, 2rem);  
        color: #fff;  
        display: flex;  
        align-items: center;  
        gap: 20px;  
        margin-bottom: 30px;  
        font-family: 'Hind Siliguri', sans-serif;  
    }  

    .glass-card h2 i {  
        color: var(--accent);  
        background: rgba(0, 242, 255, 0.1);  
        width: 60px;  
        height: 60px;  
        min-width: 60px;  
        display: flex;  
        align-items: center;  
        justify-content: center;  
        border-radius: 18px;  
        font-size: 1.6rem;  
        box-shadow: inset 0 0 10px rgba(0, 242, 255, 0.2);  
    }  

    .highlight { color: var(--accent); font-weight: 700; }  

    /* --- Grid & Items --- */  
    .mission-grid {  
        display: grid;  
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));  
        gap: 30px;  
        margin-top: 40px;  
    }  

    .mission-item {  
        background: rgba(255, 255, 255, 0.02);  
        border: 1px solid var(--border);  
        padding: 40px 30px;  
        border-radius: 25px;  
        text-align: center;  
        transition: 0.4s;  
    }  

    .mission-item p {  
        color: var(--text);  
        transition: color 0.3s;  
    }  

    .mission-item:hover {  
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));  
        border-color: transparent;  
        transform: scale(1.03);  
    }  

    .mission-item i { font-size: 3rem; color: var(--accent); margin-bottom: 25px; transition: 0.3s; }  
    .mission-item:hover i { color: #fff; transform: rotateY(180deg); }  
    .mission-item h3 { font-size: 1.6rem; margin-bottom: 15px; }  
    .mission-item:hover p { color: rgba(255,255,255,0.9); }  

    /* --- Workflow Steps --- */  
    .step-box {  
        background: rgba(255, 255, 255, 0.03);  
        padding: 30px;  
        border-radius: 20px;  
        border-left: 5px solid var(--primary);  
        margin-bottom: 25px;  
        transition: 0.3s;  
    }  

    .step-box:hover {  
        background: rgba(78, 92, 245, 0.05);  
    }  

    .step-title {  
        color: var(--accent);  
        font-weight: 700;  
        font-size: 1.2rem;  
        display: block;  
        margin-bottom: 10px;  
        font-family: 'Orbitron', sans-serif;  
        letter-spacing: 1px;  
    }  

    /* --- Math Formula Box --- */  
    .formula-box {  
        background: linear-gradient(90deg, #10101a 0%, #0d0d14 100%);  
        padding: 40px;  
        border-radius: 25px;  
        border: 1px solid var(--primary);  
        margin: 40px 0;  
        box-shadow: inset 0 0 20px rgba(78, 92, 245, 0.1);  
    }  

    /* --- CTA Box --- */  
    .cta-box {  
        text-align: center;  
        padding: 80px 40px;  
        background: radial-gradient(circle at center, rgba(78, 92, 245, 0.1) 0%, transparent 70%);  
        border: 1px solid var(--primary);  
        border-radius: 40px;  
        margin-top: 80px;  
        box-shadow: 0 0 50px rgba(78, 92, 245, 0.1);  
    }  

    .cta-box h2 { justify-content: center; }  

    .btn-premium {  
        background: linear-gradient(90deg, var(--primary), #7c83fd);  
        color: white;  
        padding: 18px 60px;  
        text-decoration: none;  
        border-radius: 100px;  
        font-weight: 800;  
        font-size: 1.2rem;  
        line-height: normal;  
        display: inline-block;  
        margin-top: 40px;  
        border: none;  
        box-shadow: 0 10px 30px var(--primary-glow);  
        transition: 0.3s;  
        cursor: pointer;  
        text-transform: uppercase;  
        letter-spacing: 2px;  
        max-width: 100%;  
        font-family: 'Hind Siliguri', sans-serif;  
    }  

    .btn-premium:hover {  
        transform: translateY(-3px);  
        box-shadow: 0 15px 40px var(--primary-glow);  
        filter: brightness(1.1);  
    }  

    ul { list-style: none; margin-top: 20px; }  
    ul li { margin-bottom: 15px; display: flex; align-items: flex-start; gap: 15px; }  
    ul li::before {  
        content: "\f004"; font-family: "Font Awesome 6 Free"; font-weight: 900;  
        color: var(--neon-pink); font-size: 1rem; margin-top: 5px;  
    }  

    /* --- Notice Modal Popup Styles --- */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(3, 3, 5, 0.88);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.4s ease, visibility 0.4s ease;
    }

    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .modal-card {
        background: linear-gradient(145deg, #0e1222, #080a14);
        border: 1px solid rgba(0, 242, 255, 0.3);
        border-radius: 28px;
        max-width: 520px;
        width: 100%;
        padding: 35px 28px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8), 0 0 40px rgba(78, 92, 245, 0.2);
        transform: scale(0.85) translateY(20px);
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        text-align: center;
        position: relative;
    }

    .modal-overlay.active .modal-card {
        transform: scale(1) translateY(0);
    }

    .modal-header {
        margin-bottom: 20px;
    }

    .modal-icon {
        font-size: 2.8rem;
        color: var(--accent);
        margin-bottom: 12px;
        filter: drop-shadow(0 0 15px var(--accent));
    }

    .modal-header h3 {
        font-size: 1.4rem;
        color: #fff;
        font-family: 'Hind Siliguri', sans-serif;
        font-weight: 700;
    }

    .modal-body p {
        font-size: 1.05rem;
        color: #ccc;
        line-height: 1.7;
        margin-bottom: 15px;
    }

    .modal-highlight {
        color: var(--accent) !important;
        font-weight: 700;
        background: rgba(0, 242, 255, 0.08);
        padding: 10px 16px;
        border-radius: 12px;
        border: 1px dashed rgba(0, 242, 255, 0.3);
        display: block;
    }

    .modal-badge {
        background: linear-gradient(135deg, rgba(78, 92, 245, 0.2), rgba(0, 242, 255, 0.1));
        border: 1px solid rgba(78, 92, 245, 0.4);
        padding: 14px 18px;
        border-radius: 16px;
        font-size: 0.98rem;
        color: #fff;
        margin-top: 18px;
        margin-bottom: 25px;
        line-height: 1.6;
    }

    .modal-btn {
        background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        color: #fff;
        border: none;
        padding: 14px 40px;
        border-radius: 50px;
        font-size: 1.1rem;
        font-weight: 700;
        font-family: 'Hind Siliguri', sans-serif;
        cursor: pointer;
        box-shadow: 0 10px 25px var(--primary-glow);
        transition: all 0.3s ease;
        width: 100%;
    }

    .modal-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px var(--primary-glow);
        filter: brightness(1.15);
    }

    /* --- Tablet --- */  
    @media (max-width: 768px) {  
        .hero-section { padding: 70px 20px; margin-bottom: 40px; }  
        .glass-card { padding: 30px 25px; }  
        .glass-card h2 { gap: 15px; }  
        .glass-card h2 i { width: 48px; height: 48px; min-width: 48px; font-size: 1.3rem; }  
        .step-box:hover { padding-left: 30px; }  
        .cta-box { padding: 50px 25px; }  
    }  

    /* --- Mobile --- */  
    @media (max-width: 480px) {  
        .container { padding: 15px; }  
        .hero-section { padding: 50px 15px; }  
        .glass-card { padding: 22px 18px; border-radius: 20px; }  
        .glass-card h2 { flex-direction: row; gap: 12px; margin-bottom: 20px; }  
        .glass-card h2 i { width: 40px; height: 40px; min-width: 40px; font-size: 1.1rem; border-radius: 12px; }  
        .mission-item { padding: 28px 20px; }  
        .mission-grid { gap: 18px; }  
        .formula-box { padding: 22px; }  
        .step-box { padding: 20px; }  
        .cta-box { padding: 35px 18px; border-radius: 25px; }  
        .btn-premium { padding: 14px 30px; font-size: 1rem; width: 100%; }  
        .modal-card { padding: 25px 18px; border-radius: 22px; }
    }  
</style>

</head>  
<body>  

<header class="hero-section">  
    <div class="container">  
        <h1>আমাদের লক্ষ্য ও উদ্দেশ্য</h1>  
        <p>"দশের লাঠি একের বোঝা"</p>  
    </div>  
</header>  

<div class="container">  

    <div class="glass-card">  
        <h2><i class="fas fa-hand-holding-heart"></i> আমাদের পরিচয়</h2>  
        <p style="font-size: 1.15rem; color: #bbb;">  
            আমরা শুধু সহকর্মী বা একই ব্যাচের পুলিশ সদস্যই নই, আমরা একে অপরের বিপদের বন্ধু, আমরা একটি পরিবার। কর্মব্যস্ত এই জীবনে আমরা হয়তো সবাই সবার খবর রাখতে পারি না। কিন্তু হঠাৎ কোনো এক কালো মেঘ যদি আমাদের কোনো ব্যাচমেটের জীবনে নেমে আসে—হোক সেটা তার নিজের অসুস্থতা, বা তার আদরের সন্তান, জীবনসঙ্গিনী কিংবা পরম শ্রদ্ধেয় বাবা-মায়ের কঠিন কোনো অসুখ—তখন যেন তাকে একাকী লড়াই করতে না হয়।  
        </p>  
        <p class="highlight" style="margin-top: 25px; font-size: 1.25rem; border-left: 4px solid var(--accent); padding-left: 20px;">  
            আমরা চাই, সেই কঠিন মুহূর্তে ৩০০০ হাত যেন একসাথে তার দিকে সহযোগিতার আশ্বাস নিয়ে এগিয়ে যায়।  
        </p>  
    </div>  

    <div class="glass-card">  
        <h2><i class="fas fa-gem"></i> আমাদের স্বপ্নের ক্যানভাস</h2>  
        <div class="mission-grid">  
            <div class="mission-item">  
                <i class="fas fa-home"></i>  
                <h3>আস্থার ঠিকানা</h3>  
                <p>আমরা এমন একটি প্ল্যাটফর্ম গড়তে চাই যেখানে আমাদের ব্যাচের কেউ নিজেকে অসহায় ভাববে না। আমরা প্রতিটি সদস্যের পরিবারের চিকিৎসা ও জরুরি প্রয়োজনে পাশে থাকব।</p>  
            </div>  
            <div class="mission-item">  
                <i class="fas fa-praying-hands"></i>  
                <h3>পরকালের পাথেয়</h3>  
                <p>আমাদের কোনো ভাই যদি পৃথিবী ছেড়ে চলে যায়, তবে তার পরিবারের প্রতি আমাদের যে দায়িত্ব থেকে যায়, তা সম্মিলিতভাবে পালন করাই আমাদের অঙ্গীকার।</p>  
            </div>  
            <div class="mission-item">  
                <i class="fas fa-link"></i>  
                <h3>একতার শিকল</h3>  
                <p>৬৪ জেলা এবং ৮ বিভাগের সকল সদস্যকে একটি ডিজিটাল ছাতার নিচে নিয়ে আসা, যেন দূরত্ব আমাদের মনের দূরত্ব না হয়ে দাঁড়ায়।</p>  
            </div>  
        </div>  
    </div>  

    <div class="glass-card">  
        <h2><i class="fas fa-sitemap"></i> কাঠামোগত বিন্যাস (Organizational Structure)</h2>  
        <div class="step-box">  
            <span class="step-title">সেন্ট্রাল অ্যাডমিন (সমন্বয় ও কমান্ড সেন্টার)</span>  
            <p>৮টি বিভাগের জন্য ৮ জন নির্বাচিত সেন্ট্রাল অ্যাডমিন মূলত একটি 'কমান্ড সেন্টার' হিসেবে কাজ করবেন। তারা নীতি নির্ধারণ এবং সমন্বয় রক্ষা করবেন। <span class="highlight">উল্লেখ্য, সেন্ট্রাল অ্যাডমিনরা সরাসরি কোনো অর্থ লেনদেন করবেন না।</span></p>  
        </div>  
        <div class="step-box">  
            <span class="step-title">জেলা অ্যাডমিন (District Admin)</span>  
            <p>প্রতিটি জেলায় ২-৩ জন বিশ্বস্ত সদস্য অ্যাডমিন হিসেবে দায়িত্ব পালন করবেন। তারা সরাসরি মাঠ পর্যায়ের সদস্য এবং সেন্ট্রাল বডির মধ্যকার সেতু হিসেবে কাজ করবেন।</p>  
        </div>  
        <div class="step-box">  
            <span class="step-title">সদস্য (Members)</span>  
            <p>পুলিশ ব্যাচের ৩০০০ সদস্য, যারা এই ফান্ডের মূল প্রাণ এবং চালিকাশক্তি।</p>  
        </div>  
    </div>  

    <div class="glass-card">  
        <h2><i class="fas fa-cogs"></i> পরিচালনা পদ্ধতি ও স্বচ্ছতা</h2>  

        <h3 style="color: var(--accent); margin-bottom: 15px; font-size: 1.4rem;">১. সদস্যভুক্তি ও প্রোফাইল</h3>  
        <p style="margin-bottom: 30px;">রেজিস্ট্রেশন রিকোয়েস্ট পাঠানোর পর সংশ্লিষ্ট জেলা অ্যাডমিন পরিচয় নিশ্চিত করে তা 'Accept' করবেন। এরপরই সদস্য তার ড্যাশবোর্ড ব্যবহার করতে পারবেন।</p>  

        <h3 style="color: var(--accent); margin-bottom: 15px; font-size: 1.4rem;">২. তহবিল সংগ্রহ (Funding Process)</h3>  
        <p style="margin-bottom: 30px;">প্রত্যেক সদস্য প্রতি মাসের <span class="highlight">৫ তারিখের মধ্যে ৫০ টাকা</span> নিজ জেলার অ্যাডমিনের কাছে জমা দেবেন এবং ওয়েবসাইট থেকে প্রমাণসহ রিকোয়েস্ট পাঠাবেন। অ্যাডমিন চেক করে একসেপ্ট করলে সাথে সাথে তা টোটাল ফান্ডে যুক্ত হবে।</p>  

        <h3 style="color: var(--accent); margin-bottom: 20px; font-size: 1.4rem;">৩. সাহায্য প্রাপ্তি ও অপারেশনাল চেইন</h3>  
        <div class="step-box" style="border-left-color: var(--accent);">  
            <p>কোনো মেম্বার যদি গুরুতর পরিস্থিতিতে পড়েন, তবে তিনি বিষয়টি তার <span class="highlight">জেলা অ্যাডমিনকে</span> জানাবেন এবং সকল প্রয়োজনীয় ডকুমেন্টস সাবমিট করবেন।</p>  
        </div>  
        <div class="step-box" style="border-left-color: var(--accent);">  
            <p>জেলা অ্যাডমিন বিষয়টি যাচাই করে <span class="highlight">সেন্ট্রাল অ্যাডমিন</span> বরাবর প্রেরণ করবেন। সেন্ট্রাল অ্যাডমিন তা বিবেচনা করে টোটাল ফান্ডিং থেকে একটি পার্সেন্টেজ (%) নির্ধারণ করবেন।</p>  
        </div>  
        <div class="step-box" style="border-left-color: var(--accent);">  
            <p>পার্সেন্টেজ নির্ধারণের সাথে সাথে প্রত্যেক জেলা অ্যাডমিনের কাছে একটি ডিজিটাল রিকোয়ারমেন্ট চলে যাবে। অ্যাডমিন নির্দিষ্ট পরিমাণ টাকা পাঠিয়ে <span class="highlight">পেমেন্ট প্রুফ ও স্ক্রিনশট</span> সাবমিট করলে মেইন ড্যাশবোর্ড থেকে তা আপডেট হবে। সম্পূর্ণ প্রক্রিয়াটি স্বচ্ছভাবে সকলের সামনে দৃশ্যমান থাকবে।</p>  
        </div>  

        <div class="formula-box">  
            <h3 style="color: #fff; margin-bottom: 15px; font-size: 1.4rem;">৪. পার্সেন্টেজ পদ্ধতি (The Percentage Formula)</h3>  
            <p style="font-size: 1.1rem;">সহায়তা বন্টনের ক্ষেত্রে আমরা 'সমতা ও জনবল ভিত্তিক' গাণিতিক পদ্ধতি অনুসরণ করি। <strong>কেন পার্সেন্টেজ?</strong> যদি ১০ জনের জেলা থেকে ২০% হিসেবে ৪০০ টাকা আসে, ১০০ জনের জেলা থেকে আসবে ২৪০০ টাকা। এতে করে সবার ওপর চাপের সমতা বজায় থাকবে।</p>  
        </div>  
    </div>  

    <div class="glass-card">  
        <h2><i class="fas fa-poll"></i> যাচাই ও পোলিং সিস্টেম</h2>  
        <p style="font-size: 1.1rem; color: #bbb;">  
            কোনো আবেদন নিয়ে সংশয় দেখা দিলে, তা <span class="highlight">সকল সদস্যের মতামতের (Polling)</span> জন্য উন্মুক্ত করা হবে। প্রতিটি ভোট ওয়েবসাইটেই স্বচ্ছভাবে রেকর্ড থাকবে, যাতে পুরো প্রক্রিয়াটি সবার কাছে দৃশ্যমান থাকে। এতে কেউ অন্যায় চাপ দিয়ে ফান্ড আদায় করতে পারবে না, আর সত্যিকারের প্রয়োজনে থাকা ভাই পাবেন দ্রুত ও নিশ্চিত সহায়তা।  
        </p>  
    </div>  

    <!-- ১. নতুন সংযোজন: রানিং বেসিকের ১% অনুদান নীতি (কর্মরত অবস্থায় নিহত ব্যাচমেটদের জন্য) -->
    <div class="glass-card" style="border-left: 6px solid var(--accent);">  
        <h2><i class="fas fa-hand-holding-medical"></i> কর্মরত অবস্থায় অকালে মৃত্যুবরণকারী ব্যাচমেটের পরিবারের জন্য ১% সহায়তা নীতি</h2>  
        <p style="font-size: 1.1rem; color: #bbb;">  
            আমাদের কোনো প্রিয় ব্যাচমেট যদি চাকরিতে বহাল থাকা অবস্থায় অকালে মৃত্যুবরণ করেন, তবে তার পরিবার যেন তাৎক্ষণিকভাবে একটি সুদৃঢ় ও সম্মানজনক আর্থিক ভিত্তি পায়—তা নিশ্চিত করতে আমরা বিশেষ অনুদান নীতি নির্ধারণ করেছি।  
        </p>  

        <div class="step-box" style="border-left-color: var(--accent); margin-top: 20px;">  
            <span class="step-title">রানিং বেসিকের ১% অনুদান নির্ধারণ</span>  
            <p style="font-size: 1.1rem;">  
                উক্ত বছরে প্রতিটি সদস্যের রানিং বেসিক স্কেলের <span class="highlight">১% (এক শতাংশ) সমপরিমাণ অর্থ</span> ওই মৃত ব্যাচমেটের পরিবারকে সরাসরি সহায়তা হিসেবে প্রদান করা হবে।  
            </p>  
        </div>  

        <div class="formula-box" style="margin-top: 20px; margin-bottom: 0;">  
            <h3 style="color: #fff; margin-bottom: 12px; font-size: 1.3rem;">
                <i class="fas fa-calculator" style="color: var(--accent); margin-right: 8px;"></i> নীতিটির সহজ উদাহরণ ও সুদূরপ্রসারী উদ্দেশ্য
            </h3>  
            <p style="font-size: 1.05rem; color: #ddd; margin-bottom: 12px;">  
                <strong>সহজ উদাহরণ:</strong> যদি কোনো সদস্যের বর্তমান রানিং বেসিক স্কেল <span class="highlight">২১,৪০০ টাকা</span> হয়, তবে তার ১% হিসেবে অনুদানের পরিমাণ হবে মাত্র <span class="highlight">২১৪ টাকা</span>। এভাবে ব্যাচের ৩,০০০ সদস্যের ক্ষুদ্র ১% অবদান একত্রিত হয়ে নিহত ব্যাচমেটের পরিবারের হাতে এককালীন অনেক বড় ও সম্মানজনক একটি আর্থিক তহবিল তুলে দেওয়া সম্ভব হবে।  
            </p>  
            <p style="font-size: 1.05rem; color: #ddd;">  
                <strong>কেন ১% বেসিক স্কেল নির্ধারণ করা হলো?</strong> সময়ের পরিবর্তনের সাথে সাথে দ্রব্যমূল্য বৃদ্ধি পায় এবং সরকারি স্কেল বাড়ার সাথে সাথে বেতনও বাড়ে। ফিক্সড কোনো নির্দিষ্ট অঙ্ক নির্ধারণ না করে 'রানিং বেসিকের ১%' নির্ধারণ করার ফলে—ভবিষ্যতে বেসিক বেতন বৃদ্ধির সাথে সাথে সাহায্যের পরিমাণও স্বয়ংসক্রিয়ভাবে বৃদ্ধি পাবে এবং সমসাময়িক পরিস্থিতির সাথে সর্বদা সামঞ্জস্যপূর্ণ থাকবে।  
            </p>  
        </div>  
    </div>  

    <div class="glass-card" style="border-left: 6px solid var(--neon-pink);">  
        <h2><i class="fas fa-heart" style="color: var(--neon-pink);"></i> মৃত ব্যাচমেটদের পরিবারের জন্য ভালোবাসা</h2>  
        <p style="font-size: 1.1rem;">বছর শেষে যদি ফান্ডের টাকা অব্যবহৃত থাকে:</p>  
        <ul>  
            <li><strong>চিরবিদায়ী বন্ধুদের জন্য:</strong> সংগৃহীত ফান্ডের <span class="highlight">৫০% থেকে ৬০% টাকা</span> মৃত ব্যাচমেটদের পরিবারের কল্যাণে প্রদান করা হবে।</li>  
            <li><strong>ভবিষ্যৎ তহবিল:</strong> অবশিষ্ট অংশ পরবর্তী বছরের 'রিজার্ভ ফান্ড' হিসেবে সংরক্ষিত থাকবে।</li>  
        </ul>  
    </div>  

    <div class="glass-card">  
        <h2><i class="fas fa-shield-alt"></i> কেন এই পদ্ধতিটি সেরা ও নিরাপদ?</h2>  
        <p style="margin-bottom: 20px;"><i class="fas fa-check-circle" style="color: var(--accent); margin-right: 10px;"></i> <strong>আমানত রক্ষা:</strong> টাকা কোনো একক ব্যক্তির কাছে থাকবে না। টাকা থাকবে জেলা প্রতিনিধিদের কাছে এবং ডিজিটাল হিসাব থাকবে ওয়েবসাইটে।</p>  
        <p><i class="fas fa-check-circle" style="color: var(--accent); margin-right: 10px;"></i> <strong>পরস্পরের ঢাল:</strong> এটি কোনো ব্যবসায়িক স্কিম নয়; এটি বিপদে একে অপরের ঢাল হয়ে দাঁড়ানোর একটি ক্ষুদ্র প্রচেষ্টা।</p>  
    </div>  

    <div class="cta-box">  
        <h2 style="font-family: 'Orbitron'; letter-spacing: 2px;"><i class="fas fa-handshake"></i> আমাদের আহ্বান</h2>  
        <p style="font-size: 1.2rem; max-width: 850px; margin: 0 auto; color: #ccc;">  
            এটি কোনো দান বা খয়রাত নয়; এটি আপনার নিজের জন্য, আপনার প্রিয় পরিবারের জন্য একটি <strong>'পারস্পরিক আমানত'</strong>। আসুন, কাঁধে কাঁধ মিলিয়ে প্রমাণ করি—আমরা পুলিশ ব্যাচ একে অপরের পরম আপনজন।  
        </p>  
        <p style="font-weight: 700; font-style: italic; margin-top: 30px; color: var(--accent); font-size: 1.1rem;">  
            "আসুন আমরা ঐক্যবদ্ধ হই। আমাদের এই ছোট উদ্যোগটিই হোক আমাদের ব্যাচের ইতিহাসের সবচেয়ে সুন্দর অধ্যায়।"  
        </p>  
        <a href="dashboard.php" class="btn-premium">ধন্যবাদ</a>  
    </div>  
</div>

<!-- ২. নতুন সংযোজন: অটোমেটিক পপ-আপ নোটিফিকেশন (Modal Notice Popup) -->
<div id="noticeModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <i class="fas fa-bullhorn modal-icon"></i>
            <h3>জরুরি বিজ্ঞপ্তি ও আবগতকরণ</h3>
        </div>
        <div class="modal-body">
            <p>
                এই ওয়েবসাইটটিতে আপনারা হয়তো কিছু অ্যামাউন্ট ট্রানজেকশন বা ডেমো প্রজেক্ট রান হতে দেখছেন। ওয়েবসাইটটিকে সম্পূর্ণ নিখুঁত, নির্ভরযোগ্য এবং সেরা থেকে সেরা (Best of the Best) করার উদ্দেশ্যে বর্তমানে ট্রায়াল ও ডেভেলপমেন্ট কাজ চলমান রয়েছে। তাই বিষয়টিকে দয়া করে কেউ ভুল বুঝবেন না।
            </p>
            <p class="modal-highlight">
                আপনারা যার যার অবস্থান থেকে আমাদের ব্যাচমেটদের যুক্ত (Members Join) করাতে থাকুন।
            </p>
            <div class="modal-badge">
                <i class="fas fa-star" style="color: #fbbf24;"></i>
                ইনশাআল্লাহ, মহান আল্লাহ বাঁচিয়ে রাখলে আমাদের ব্যাচের <strong>৫ম বার্ষিকী (5th Anniversary)</strong>-র আগেই আমরা এই প্রজেক্টের কাজ সফলভাবে সম্পন্ন করব।
            </div>
        </div>
        <button type="button" class="modal-btn" onclick="closeNoticeModal()">
            <i class="fas fa-check-circle"></i> বুঝেছি / ঠিক আছে
        </button>
    </div>
</div>

<script>
    // পেজ লোড হওয়ার সাথে সাথে পপ-আপ নোটিফিকেশন ওপেন হওয়ার স্ক্রিপ্ট
    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById("noticeModal");
        if (modal) {
            setTimeout(function() {
                modal.classList.add("active");
            }, 300); // হালকা স্মুথ ডিলে সহ ওপেন হবে
        }
    });

    // পপ-আপ বন্ধ করার ফাংশন
    function closeNoticeModal() {
        const modal = document.getElementById("noticeModal");
        if (modal) {
            modal.classList.remove("active");
        }
    }
</script>

</body>  
</html>