@php
    $landingPage = \App\Models\LandingPage::first() ?? new \App\Models\LandingPage();
    $landingTheme = in_array($landingPage->theme, ['corporate','luxury','vibrant']) ? $landingPage->theme : 'corporate';
    $actualProjects = ($landingPage->projects_mode === 'actual' && !empty($landingPage->project_ids))
        ? \App\Models\Project::whereIn('id',$landingPage->project_ids)->where('is_active',1)->get()
        : collect();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1730">
    <meta name="description" content="BlueMarketing helps you explore real estate opportunities with clear guidance, thoughtful service, and confidence at every step.">
    <title>BlueMarketing | Property Decisions, Made Clear</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
      :root{--navy:#101d38;--dark:#091429;--blue:#2563df;--gold:#d8ad66;--cream:#f8f7f3;--text:#26354e;--muted:#66748c;--line:#e4e9f0;--white:#fff;--shadow:0 22px 70px rgba(12,31,59,.12)}
      *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:"DM Sans",sans-serif;color:var(--text);background:#fff;line-height:1.6}h1,h2,h3,h4{font-family:Manrope,sans-serif;line-height:1.17;letter-spacing:-.04em;color:var(--navy)}a{text-decoration:none;color:inherit}button,input,select,textarea{font:inherit}button{cursor:pointer}.container{width:min(1160px,calc(100% - 42px));margin:auto}
      .eyebrow{display:inline-flex;align-items:center;gap:10px;text-transform:uppercase;letter-spacing:.16em;font-size:11px;font-weight:800;color:#bc8c44}.eyebrow:before{content:"";width:28px;height:1px;background:currentColor}
      .btn{display:inline-flex;align-items:center;justify-content:center;gap:12px;padding:15px 22px;border-radius:7px;font-size:14px;font-weight:700;border:1px solid transparent;transition:transform .2s,background .2s,box-shadow .2s}.btn:hover{transform:translateY(-2px)}.btn-primary{color:white;background:var(--blue);box-shadow:0 10px 24px rgba(37,99,223,.2)}.btn-primary:hover{background:#164fc0}.btn-outline{color:var(--navy);background:#fff;border-color:#cbd5e4}.btn-outline:hover{border-color:var(--blue)}.btn-white{color:var(--navy);background:#fff}
      .topline{background:var(--dark);color:#c5d1e5;font-size:12px}.topline .container{min-height:36px;display:flex;align-items:center;justify-content:space-between;gap:14px}.topline strong{color:#fff}.nav{height:82px;background:#fff;position:sticky;top:0;z-index:30;border-bottom:1px solid #edf0f4}.nav-inner{display:flex;align-items:center;justify-content:space-between;height:100%;gap:28px}.brand{display:inline-flex;align-items:center;gap:12px;color:var(--navy);font:800 21px Manrope,sans-serif;letter-spacing:-.055em;white-space:nowrap}.brand-mark{width:39px;height:39px;display:grid;place-items:center;background:var(--navy);border-radius:9px;color:#fff;font:800 19px Manrope}.brand .accent{color:var(--blue)}.nav-links{display:flex;align-items:center;gap:28px;font-size:13px;font-weight:700;color:#53627a}.nav-links a:hover{color:var(--blue)}.nav-actions{display:flex;align-items:center;gap:12px}.nav-actions .btn{padding:11px 17px}.menu-toggle{display:none;border:0;background:transparent;font-size:24px;color:var(--navy)}
      .hero{background:var(--cream);position:relative;overflow:hidden}.hero-grid{min-height:660px;display:grid;grid-template-columns:1.02fr .98fr;align-items:center;gap:30px;padding:54px 0 76px}.hero h1{font-size:clamp(42px,5.2vw,68px);margin:20px 0 22px;font-weight:800;max-width:650px}.hero h1 span{color:var(--blue)}.hero p{color:#5b6980;font-size:17px;max-width:510px;margin:0 0 28px}.hero-buttons{display:flex;flex-wrap:wrap;gap:12px}.hero-note{margin-top:26px;display:flex;align-items:center;gap:12px;font-size:12px;font-weight:700;color:#657187}.hero-note .dot{height:8px;width:8px;background:#33a582;border-radius:50%}.hero-photo{position:relative;min-height:520px;align-self:stretch;border-radius:5px;background:#bfcbd6 url("https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1500&q=85") center/cover}.hero-photo:before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 45%,rgba(8,20,40,.43))}.hero-photo .image-label{position:absolute;bottom:26px;left:27px;color:white;font-size:12px;letter-spacing:.06em;text-transform:uppercase;font-weight:700}.hero-card{position:absolute;left:-45px;top:52px;background:#fff;box-shadow:var(--shadow);padding:19px 22px;border-left:4px solid var(--gold);max-width:230px}.hero-card strong{display:block;font:800 18px Manrope;color:var(--navy)}.hero-card span{font-size:12px;color:#6e7b8f}
      .proofbar{background:var(--navy);color:white}.proof-inner{padding:23px 0;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap}.proof-inner span{font-size:13px;color:#d7e0ef}.proof-inner strong{color:white}.proof-sep{height:26px;width:1px;background:#45546d}
      section{scroll-margin-top:100px}.section{padding:105px 0}.section-heading{max-width:660px;margin-bottom:43px}.section-heading h2{font-size:clamp(30px,4vw,45px);margin:15px 0}.section-heading p{color:var(--muted);margin:0;font-size:15px}.center{text-align:center;margin-left:auto;margin-right:auto}.center .eyebrow:before{display:none}
      .service-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}.service-card{padding:32px;border:1px solid var(--line);border-radius:9px;background:#fff;transition:box-shadow .2s,transform .2s}.service-card:hover{transform:translateY(-4px);box-shadow:var(--shadow)}.service-icon{font-size:25px;width:56px;height:56px;background:#edf3ff;color:var(--blue);display:grid;place-items:center;border-radius:10px;margin-bottom:28px}.service-card h3{font-size:19px;margin:0 0 11px}.service-card p{font-size:14px;color:var(--muted);margin:0}
      .about{background:var(--cream)}.about-grid{display:grid;grid-template-columns:1fr 1fr;gap:78px;align-items:center}.about-photo{height:480px;background:#cad7de url("https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1300&q=85") center/cover;border-radius:7px}.about-copy h2{font-size:clamp(31px,4vw,47px);margin:16px 0 20px}.about-copy p{color:var(--muted);font-size:15px}.check-list{list-style:none;padding:0;margin:26px 0}.check-list li{display:flex;gap:13px;margin:15px 0;font-weight:700;font-size:14px}.check-list li:before{content:"✓";display:grid;place-items:center;width:23px;height:23px;flex:none;background:#e7efff;color:var(--blue);border-radius:50%;font-weight:800}
      .projects-intro{display:flex;align-items:end;justify-content:space-between;gap:20px}.projects-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}.project-card{border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#fff}.project-img{height:240px;background-size:cover;background-position:center}.project-img.one{background-image:url("https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=950&q=80")}.project-img.two{background-image:url("https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=950&q=80")}.project-img.three{background-image:url("https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=950&q=80")}.project-body{padding:25px}.project-body small{font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:#bf8b47;font-weight:800}.project-body h3{font-size:20px;margin:10px 0}.project-body p{margin:0;color:var(--muted);font-size:13px}.project-body a{display:inline-block;color:var(--blue);font-size:13px;font-weight:800;margin-top:20px}.disclaimer{font-size:12px;color:#6a7688;margin-top:20px}
      .process{background:#f3f6fb}.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:25px}.step{padding:10px 25px 10px 0}.step b{font:800 54px Manrope;color:#d9e3f4}.step h3{margin:14px 0;font-size:20px}.step p{font-size:14px;color:var(--muted)}
      .cta{background:var(--navy);color:white;padding:70px 0}.cta-inner{display:flex;justify-content:space-between;align-items:center;gap:30px}.cta h2{color:#fff;font-size:clamp(30px,4vw,43px);max-width:640px;margin:11px 0}.cta p{color:#c5d0e2;margin:0}.cta .eyebrow{color:#f1c889}
      .faq-grid{display:grid;grid-template-columns:.8fr 1.2fr;gap:60px}.faq-grid h2{font-size:40px;margin:15px 0}.faq-grid p{color:var(--muted)}details{border-bottom:1px solid var(--line);padding:20px 0}summary{font-weight:800;font-size:15px;cursor:pointer;color:var(--navy);list-style:none;display:flex;justify-content:space-between;gap:20px}summary:after{content:"+";color:var(--blue);font-size:22px}details[open] summary:after{content:"−"}details p{font-size:14px;margin:12px 0 0}
      .contact{background:var(--cream)}.contact-grid{display:grid;grid-template-columns:.9fr 1.1fr;gap:70px}.contact h2{font-size:clamp(31px,4vw,44px);margin:16px 0}.contact p{color:var(--muted)}.contact-points{margin-top:30px}.contact-points div{margin:16px 0;font-weight:700;font-size:14px}.contact-form{background:white;padding:37px;border:1px solid var(--line);box-shadow:0 15px 45px rgba(13,27,54,.05);border-radius:10px}.contact-form h3{margin:0 0 6px;font-size:22px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:17px}.field{display:flex;flex-direction:column;gap:7px;margin:17px 0}.field label{font-size:12px;font-weight:800;color:#34435a}.field input,.field select,.field textarea{background:#fff;border:1px solid #d9e0e9;border-radius:6px;padding:12px 14px;color:var(--navy);width:100%;outline:none}.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px #e5edff}.field textarea{resize:vertical;min-height:95px}.form-hint{font-size:12px;color:#78859a;margin-top:13px}.contact-form .btn{width:100%}.form-pending{background:#fff8e9;border:1px solid #f1dcb1;padding:12px;border-radius:6px;color:#8b6428;font-size:12px;margin-bottom:16px}
      footer{background:#081326;color:#b5c3d7;padding:54px 0 25px}.footer-grid{display:flex;align-items:start;justify-content:space-between;gap:30px}.footer-brand{color:#fff}.footer-copy{font-size:13px;max-width:380px}.footer-links{display:flex;gap:23px;flex-wrap:wrap;font-size:13px}.footer-links a:hover{color:#fff}.copyright{border-top:1px solid #2b3951;padding-top:23px;margin-top:45px;display:flex;justify-content:space-between;gap:15px;font-size:12px}
      @media(max-width:960px){.nav-links{gap:15px}.nav-actions .btn{display:none}.hero-grid{grid-template-columns:1fr 1fr}.hero-photo{min-height:460px}.hero-card{left:-20px}.service-grid,.projects-grid{gap:14px}.about-grid{gap:35px}.contact-grid{gap:30px}}
      @media(max-width:760px){.topline{display:none}.nav{height:70px}.menu-toggle{display:block}.nav-links{display:none;position:absolute;left:0;right:0;top:70px;background:white;flex-direction:column;align-items:stretch;padding:23px;border-top:1px solid var(--line);box-shadow:0 15px 25px #09142918}.nav-links.open{display:flex}.hero-grid{display:flex;flex-direction:column;align-items:stretch;padding:56px 0 50px;gap:42px}.hero h1{font-size:43px}.hero-photo{min-height:365px}.hero-card{left:15px;top:18px}.proof-inner{justify-content:center;text-align:center}.proof-sep{display:none}.section{padding:75px 0}.service-grid,.projects-grid,.steps{grid-template-columns:1fr}.about-grid,.faq-grid,.contact-grid{grid-template-columns:1fr}.about-photo{height:330px}.cta-inner,.projects-intro{align-items:start;flex-direction:column}.form-grid{grid-template-columns:1fr;gap:0}.contact-form{padding:23px}.footer-grid,.copyright{flex-direction:column}.project-img{height:220px}}
      @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*{transition:none!important}}
    </style>

    <style>
      /* Website themes: markup stays shared while brand presentation changes. */
      body.theme-luxury{--navy:#ded6c8;--dark:#090d14;--blue:#c49b5f;--gold:#d5ae72;--cream:#121820;--text:#d1d0cf;--muted:#a6aebc;--line:#303a48;--white:#101822;background:#0e141c;color:#d1d0cf}
      body.theme-luxury h1,body.theme-luxury h2,body.theme-luxury h3,body.theme-luxury h4{color:#f3f1ed}
      body.theme-luxury .nav,body.theme-luxury .service-card,body.theme-luxury .project-card,body.theme-luxury .contact-form{background:#141c27;border-color:#303a48}
      body.theme-luxury .brand,body.theme-luxury .nav-links,body.theme-luxury .nav-actions .btn-outline{color:#f2f0e9}
      body.theme-luxury .btn-primary{color:#151515;background:#d6b17b;box-shadow:none}
      body.theme-luxury .btn-outline{background:transparent;color:#e5dac9;border-color:#596478}
      body.theme-luxury .hero,body.theme-luxury .about,body.theme-luxury .process,body.theme-luxury .contact{background:#101823}
      body.theme-luxury .hero h1 span{color:#d6b17b}
      body.theme-luxury .hero-photo{filter:saturate(.7)}
      body.theme-luxury .hero-card,body.theme-luxury .service-icon{background:#222d3c;color:#e7c38d}
      body.theme-luxury .hero-card strong,body.theme-luxury .project-body h3,body.theme-luxury summary{color:#fff}
      body.theme-luxury .field input,body.theme-luxury .field select,body.theme-luxury .field textarea{background:#202a36;color:#fff;border-color:#465363}
      body.theme-luxury .field label{color:#ddd}
      body.theme-luxury .form-pending{background:#362d1d;border-color:#705631;color:#f5d19a}
      body.theme-luxury .cta,body.theme-luxury footer{background:#080c13}
      body.theme-vibrant{--navy:#122a65;--dark:#102969;--blue:#155cf9;--gold:#26bcde;--cream:#edf6ff;--text:#294069;--muted:#5f7298;--line:#d6e5fb;--white:#fff}
      body.theme-vibrant .hero{background:linear-gradient(135deg,#f4f9ff 0%,#e1edff 55%,#eff7ff 100%)}
      body.theme-vibrant .hero h1 span{background:linear-gradient(110deg,#2055e7,#12a4d9);-webkit-background-clip:text;background-clip:text;color:transparent}
      body.theme-vibrant .btn-primary{background:linear-gradient(105deg,#1757f5,#177ecf);box-shadow:0 12px 25px #2663df38;border-radius:12px}
      body.theme-vibrant .service-card,body.theme-vibrant .project-card{border-radius:18px}
      body.theme-vibrant .hero-photo,body.theme-vibrant .about-photo{border-radius:20px}
      body.theme-vibrant .proofbar,body.theme-vibrant .cta{background:linear-gradient(100deg,#10276b,#1760de)}
      @media(max-width:760px){body.theme-luxury .nav-links{background:#141c27}}
    </style>
</head>
<body class="theme-{{ $landingTheme }}">
<div class="topline"><div class="container"><span>Thoughtful guidance for your next property decision.</span><span>Welcome to <strong>BlueMarketing</strong></span></div></div>
<header class="nav">
  <div class="container nav-inner">
    <a class="brand" href="#top" aria-label="BlueMarketing Home"><span class="brand-mark">B</span><span>Blue<span class="accent">Marketing</span></span></a>
    <nav class="nav-links" id="site-menu" aria-label="Main navigation">
      <a href="#about">About</a><a href="#services">Our Services</a><a href="#opportunities">Explore</a><a href="#process">How It Works</a><a href="#contact">Contact</a>
    </nav>
    <div class="nav-actions"><a class="btn btn-outline" href="{{ route('login') }}">Staff Login</a><a class="btn btn-primary" href="#contact">Talk to Our Team <span aria-hidden="true">↗</span></a><button class="menu-toggle" id="menu-toggle" type="button" aria-label="Toggle navigation" aria-controls="site-menu" aria-expanded="false">☰</button></div>
  </div>
</header>
@if(!empty($isLandingPreview))
<div style="background:#ffefbd;color:#573a0c;padding:10px 20px;text-align:center;font-size:14px;font-weight:700">DRAFT PREVIEW — changes are not yet published</div>
@endif
<main id="top">
  <section class="hero" aria-labelledby="hero-title"><div class="container hero-grid">
    <div><span class="eyebrow">{{ $landingPage->contentValue('hero_eyebrow') }}</span><h1 id="hero-title">{{ $landingPage->contentValue('hero_title') }}</h1><p>{{ $landingPage->contentValue('hero_description') }}</p>
      <div class="hero-buttons"><a class="btn btn-primary" href="#contact">Explore Your Options <span aria-hidden="true">↗</span></a><a class="btn btn-outline" href="#about">Get to Know Us <span aria-hidden="true">→</span></a></div>
      <div class="hero-note"><span class="dot"></span>Personal attention. Transparent conversations. Clear next steps.</div>
    </div>
    <div class="hero-photo" @if($landingPage->contentValue('hero_image')) style="background-image:url('{{ $landingPage->contentValue('hero_image') }}')" @endif role="img" aria-label="Contemporary residential architecture"><div class="hero-card"><strong>Space to dream bigger.</strong><span>Find a direction that feels right for you.</span></div><div class="image-label">Picture your possibilities</div></div>
  </div></section>
  <div class="proofbar"><div class="container proof-inner"><span><strong>People-first</strong> approach</span><i class="proof-sep"></i><span><strong>Clear</strong> communication</span><i class="proof-sep"></i><span><strong>Thoughtful</strong> property guidance</span><i class="proof-sep"></i><span><strong>One-to-one</strong> support</span></div></div>
  @if($landingPage->sectionEnabled('services'))
<section class="section" id="services"><div class="container"><div class="section-heading center"><span class="eyebrow">What we do</span><h2>{{ $landingPage->contentValue('services_title') }}</h2><p>From initial discovery to your next decision, we're here to make the experience more understandable and personal.</p></div>
    <div class="service-grid">
      <article class="service-card"><div class="service-icon" aria-hidden="true">⌂</div><h3>Property Discovery</h3><p>Explore real estate options aligned with your needs, lifestyle and priorities.</p></article>
      <article class="service-card"><div class="service-icon" aria-hidden="true">◇</div><h3>Marketing & Promotion</h3><p>Compelling presentation that helps property opportunities reach the right audience.</p></article>
      <article class="service-card"><div class="service-icon" aria-hidden="true">↗</div><h3>Customer Guidance</h3><p>Ask questions, compare options and move forward with greater clarity.</p></article>
    </div></div></section>
@endif
  @if($landingPage->sectionEnabled('about'))
<section class="section about" id="about"><div class="container about-grid"><div class="about-photo" @if($landingPage->contentValue('about_image')) style="background-image:url('{{ $landingPage->contentValue('about_image') }}')" @endif role="img" aria-label="Modern residential exterior"></div><div class="about-copy"><span class="eyebrow">About BlueMarketing</span><h2>{{ $landingPage->contentValue('about_title') }}</h2><p>{{ $landingPage->contentValue('about_description') }}</p><p>We believe good guidance starts with listening, asking the right questions and communicating clearly along the way.</p><ul class="check-list"><li>Start with your requirements and priorities</li><li>Explore options with straightforward guidance</li><li>Stay informed from inquiry to next steps</li></ul><a class="btn btn-primary" href="#contact">Start a Conversation <span aria-hidden="true">↗</span></a></div></div></section>
@endif
  @if($landingPage->sectionEnabled('opportunities'))
<section class="section" id="opportunities"><div class="container"><div class="projects-intro"><div class="section-heading"><span class="eyebrow">Explore possibilities</span><h2>{{ $landingPage->contentValue('projects_title') }}</h2><p>Whether you are exploring a new home, a plot or a commercial opportunity, start by choosing what matters most.</p></div><a class="btn btn-outline" href="#contact">Discuss Your Requirements →</a></div>
    @if($actualProjects->isNotEmpty())
    <div class="projects-grid">
      @foreach($actualProjects as $project)
        <article class="project-card">
          <div class="project-img one" role="img" aria-label="Project property illustration"></div>
          <div class="project-body"><small>Featured project</small><h3>{{ $project->project }}</h3><p>{{ $project->address }}</p><a href="#contact">Ask About This Project →</a></div>
        </article>
      @endforeach
    </div>
    @else
    <div class="projects-grid">
      <article class="project-card"><div class="project-img one" role="img" aria-label="Elegant residential interior"></div><div class="project-body"><small>Residential</small><h3>A place to call home</h3><p>Discover spaces designed around everyday living.</p><a href="#contact">Ask About Homes →</a></div></article>
      <article class="project-card"><div class="project-img two" role="img" aria-label="Refined contemporary property interior"></div><div class="project-body"><small>Investment</small><h3>Room for your plans</h3><p>Talk through property opportunities and your goals.</p><a href="#contact">Explore Options →</a></div></article>
      <article class="project-card"><div class="project-img three" role="img" aria-label="Modern building exterior"></div><div class="project-body"><small>Commercial</small><h3>Space for what comes next</h3><p>Find a direction for your business ambitions.</p><a href="#contact">Make an Inquiry →</a></div></article>
    </div>
    @endif<p class="disclaimer">Images are illustrative. Confirm project details and availability with our team.</p></div></section>
@endif
  @if($landingPage->sectionEnabled('process'))
<section class="section process" id="process"><div class="container"><div class="section-heading center"><span class="eyebrow">Simple by design</span><h2>Good decisions start with a conversation.</h2><p>We keep the first steps simple, so you can focus on finding the right fit.</p></div><div class="steps"><article class="step"><b>01</b><h3>Tell us your goals</h3><p>Share what you're looking for, what matters to you and your preferred budget.</p></article><article class="step"><b>02</b><h3>Discuss your options</h3><p>Connect with our team to explore relevant possibilities and get your questions answered.</p></article><article class="step"><b>03</b><h3>Choose your next step</h3><p>Move ahead at a pace that suits you, with clarity about the process.</p></article></div></div></section>
@endif
  <section class="cta"><div class="container cta-inner"><div><span class="eyebrow">Let's talk property</span><h2>{{ $landingPage->contentValue('cta_title') }}</h2><p>Tell us what you're looking for and let's explore where to begin.</p></div><a class="btn btn-white" href="#contact">Get in Touch <span aria-hidden="true">↗</span></a></div></section>
  @if($landingPage->sectionEnabled('faq'))
<section class="section" id="faq"><div class="container faq-grid"><div><span class="eyebrow">Helpful answers</span><h2>Questions? You're in the right place.</h2><p>Here are a few common questions to help you get started.</p></div><div><details><summary>How do I get started with BlueMarketing?</summary><p>Share your requirements with the team. We can then discuss your interests and the best next steps.</p></details><details><summary>Can I discuss both residential and commercial options?</summary><p>Yes. Tell us which type of property you're considering so the conversation can focus on your goals.</p></details><details><summary>What should I prepare before making an inquiry?</summary><p>A preferred location, an approximate budget and the purpose of the property are useful starting points.</p></details><details><summary>Are the photos on this page actual project listings?</summary><p>No. The images illustrate property styles. Specific project details and availability should be confirmed directly with the team.</p></details></div></div></section>
@endif
  @if($landingPage->sectionEnabled('contact'))
<section class="section contact" id="contact"><div class="container contact-grid"><div><span class="eyebrow">Let's connect</span><h2>{{ $landingPage->contentValue('contact_title') }}</h2><p>We'd love to understand your plans and help you take the next step. The inquiry system is currently being prepared; contact details and live form submissions will be enabled after configuration.</p><div class="contact-points"><div>✓ Residential, commercial & investment interests</div><div>✓ Personalized discussion of your requirements</div><div>✓ No pressure — just a clearer starting point</div></div></div>
    <div class="contact-form"><h3>Request a conversation</h3><p style="margin:0 0 18px;font-size:13px">A quick introduction helps us prepare for your inquiry.</p><div class="form-pending" role="status">Preview only: submissions are not enabled yet. No personal information is collected by this form.</div>
      <form aria-label="Inquiry form preview" onsubmit="return false">
      <div class="form-grid"><div class="field"><label for="full-name">Full name</label><input id="full-name" type="text" placeholder="Your name" autocomplete="name" disabled></div><div class="field"><label for="phone">Phone number</label><input id="phone" type="tel" placeholder="Your phone number" autocomplete="tel" disabled></div></div>
      <div class="form-grid"><div class="field"><label for="interest">I'm interested in</label><select id="interest" disabled><option>Choose a category</option><option>Residential</option><option>Commercial</option><option>Investment</option><option>General inquiry</option></select></div><div class="field"><label for="budget">Approximate budget</label><input id="budget" type="text" placeholder="Optional" disabled></div></div>
      <div class="field"><label for="message">Your requirements</label><textarea id="message" placeholder="Tell us a little about what you're looking for" disabled></textarea></div><button class="btn btn-primary" type="button" disabled aria-disabled="true">Inquiry Submission Coming Soon →</button><div class="form-hint">We'll connect this form to a secure lead-capture endpoint in the next development stage.</div>
      </form></div></div></section>
@endif
</main>
<footer><div class="container"><div class="footer-grid"><div><a class="brand footer-brand" href="#top"><span class="brand-mark" style="background:#2563df">B</span><span>Blue<span style="color:#93b5ff">Marketing</span></span></a><p class="footer-copy">{{ $landingPage->contentValue('footer_description') }}</p></div><nav class="footer-links" aria-label="Footer navigation"><a href="#about">About</a><a href="#services">Services</a><a href="#opportunities">Explore</a><a href="#faq">FAQs</a><a href="#contact">Contact</a><a href="{{ route('login') }}">Staff Login</a></nav></div><div class="copyright"><span>© {{ date('Y') }} BlueMarketing. All rights reserved.</span><span>Made with care for your next move.</span></div></div></footer>
<script>
(function(){
  var toggle=document.getElementById('menu-toggle');
  var menu=document.getElementById('site-menu');
  if(toggle&&menu){
    toggle.addEventListener('click',function(){var open=menu.classList.toggle('open');toggle.setAttribute('aria-expanded',String(open));});
    menu.querySelectorAll('a').forEach(function(link){link.addEventListener('click',function(){menu.classList.remove('open');toggle.setAttribute('aria-expanded','false');});});
  }
})();
</script>
</body>
</html>