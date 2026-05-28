<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galadawa Textiles</title>
    <style>
        :root {
            --ink: #111827;
            --muted: #667085;
            --line: #e5e7eb;
            --brand: #123c69;
            --accent: #b88a2f;
            --soft: #f6f7f9;
            --white: #ffffff;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: var(--soft);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }

        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }

        .page {
            min-height: 100vh;
            overflow-x: hidden;
        }

        .header {
            position: fixed;
            inset: 0 0 auto 0;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 16px clamp(18px, 5vw, 72px);
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(229, 231, 235, 0.9);
            backdrop-filter: blur(18px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand img {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .brand strong {
            display: block;
            color: var(--brand);
            font-size: clamp(16px, 2vw, 20px);
            line-height: 1.1;
        }

        .brand span {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 800;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 8px;
            border: 1px solid transparent;
            font-weight: 900;
            transition: transform 180ms ease, box-shadow 180ms ease, background 180ms ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            color: var(--white);
            background: var(--brand);
            box-shadow: 0 14px 30px rgba(18, 60, 105, 0.2);
        }

        .btn-light {
            color: var(--brand);
            background: var(--white);
            border-color: var(--line);
        }

        .hero {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(360px, 1.05fr);
            align-items: center;
            gap: clamp(28px, 5vw, 72px);
            padding: 112px clamp(18px, 5vw, 72px) 54px;
            background:
                linear-gradient(90deg, rgba(246, 247, 249, 0.98), rgba(246, 247, 249, 0.76)),
                url("uploads/prod_1_6a1251fb4c84f.jpg") center / cover no-repeat;
        }

        .hero-copy {
            animation: riseIn 700ms ease both;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            color: var(--brand);
            font-size: 12px;
            font-weight: 950;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 36px;
            height: 2px;
            background: var(--accent);
        }

        h1 {
            max-width: 660px;
            font-size: clamp(46px, 7.5vw, 92px);
            line-height: 0.95;
            letter-spacing: 0;
            color: var(--ink);
        }

        .lead {
            max-width: 540px;
            margin-top: 24px;
            color: var(--muted);
            font-size: clamp(16px, 2vw, 20px);
            line-height: 1.65;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 34px;
        }

        .hero-gallery {
            display: grid;
            grid-template-columns: 1fr 0.78fr;
            gap: 16px;
            min-height: 570px;
            animation: fadeIn 900ms ease 120ms both;
        }

        .photo {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            background: #d9dee7;
            box-shadow: 0 24px 60px rgba(17, 24, 39, 0.18);
        }

        .photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scale(1.03);
            animation: slowZoom 9s ease-in-out infinite alternate;
        }

        .photo.large { min-height: 570px; }

        .photo-stack {
            display: grid;
            gap: 16px;
        }

        .photo-stack .photo { min-height: 277px; }

        .stat-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            background: var(--white);
        }

        .stat {
            min-height: 128px;
            padding: 30px clamp(18px, 5vw, 72px);
            border-right: 1px solid var(--line);
        }

        .stat:last-child { border-right: 0; }
        .stat strong { display: block; color: var(--brand); font-size: clamp(26px, 3vw, 38px); }
        .stat span { display: block; margin-top: 8px; color: var(--muted); font-weight: 750; line-height: 1.45; }

        .section {
            padding: clamp(56px, 8vw, 96px) clamp(18px, 5vw, 72px);
        }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 28px;
            margin-bottom: 30px;
        }

        .section-head h2 {
            max-width: 620px;
            font-size: clamp(30px, 4vw, 48px);
            line-height: 1.08;
            letter-spacing: 0;
        }

        .section-head p {
            max-width: 420px;
            color: var(--muted);
            line-height: 1.65;
            font-weight: 650;
        }

        .collection-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr 1fr;
            gap: 18px;
        }

        .collection {
            position: relative;
            min-height: 360px;
            overflow: hidden;
            border-radius: 8px;
            background: #d9dee7;
        }

        .collection.tall { min-height: 520px; }

        .collection img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 520ms ease;
        }

        .collection:hover img { transform: scale(1.05); }

        .label {
            position: absolute;
            left: 18px;
            right: 18px;
            bottom: 18px;
            padding: 14px 16px;
            border-radius: 8px;
            color: var(--white);
            background: rgba(17, 24, 39, 0.78);
            backdrop-filter: blur(12px);
        }

        .label strong { display: block; font-size: 18px; }
        .label span { display: block; margin-top: 4px; color: rgba(255, 255, 255, 0.78); font-size: 13px; font-weight: 750; }

        .features {
            background: var(--white);
            border-top: 1px solid var(--line);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--line);
        }

        .feature {
            min-height: 184px;
            padding: 24px;
            background: var(--white);
        }

        .feature span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            margin-bottom: 24px;
            border-radius: 8px;
            color: var(--white);
            background: var(--brand);
            font-size: 13px;
            font-weight: 950;
        }

        .feature h3 { margin-bottom: 9px; font-size: 18px; }
        .feature p { color: var(--muted); font-size: 14px; line-height: 1.6; }

        .cta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
            padding: clamp(36px, 6vw, 64px) clamp(18px, 5vw, 72px);
            color: var(--white);
            background: var(--brand);
        }

        .cta h2 {
            max-width: 740px;
            font-size: clamp(30px, 4vw, 48px);
            line-height: 1.08;
            letter-spacing: 0;
        }

        .cta .btn {
            color: var(--brand);
            background: var(--white);
            box-shadow: none;
            flex-shrink: 0;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding: 24px clamp(18px, 5vw, 72px);
            color: var(--muted);
            background: var(--white);
            font-size: 13px;
            font-weight: 800;
        }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slowZoom {
            from { transform: scale(1.02); }
            to { transform: scale(1.09); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 1ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: 1ms !important;
            }
        }

        @media (max-width: 1060px) {
            .hero {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .hero-gallery {
                min-height: 440px;
            }

            .photo.large { min-height: 440px; }
            .feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 760px) {
            .header {
                position: static;
                padding: 14px 18px;
            }

            .brand span,
            .nav a:not(.btn) {
                display: none;
            }

            .hero {
                padding: 34px 18px;
            }

            h1 { font-size: clamp(40px, 15vw, 62px); }

            .actions,
            .cta {
                flex-direction: column;
                align-items: stretch;
            }

            .actions .btn,
            .cta .btn {
                width: 100%;
            }

            .hero-gallery,
            .stat-row,
            .collection-grid,
            .feature-grid {
                grid-template-columns: 1fr;
            }

            .hero-gallery,
            .photo.large,
            .photo-stack .photo,
            .collection,
            .collection.tall {
                min-height: 280px;
            }

            .section-head {
                display: block;
            }

            .section-head p {
                margin-top: 14px;
            }

            .stat {
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            .stat:last-child { border-bottom: 0; }

            .footer {
                display: block;
            }

            .footer span {
                display: block;
                margin-top: 8px;
            }
        }
    </style>
</head>
<body class="page">
    <header class="header">
        <a class="brand" href="index.php">
            <img src="img/logo.png" alt="Galadawa Textiles logo">
            <div>
                <strong>Galadawa Textiles</strong>
                <span>Store management system</span>
            </div>
        </a>
        <nav class="nav">
            <a href="#collections">Collections</a>
            <a href="#features">System</a>
            <a class="btn btn-primary" href="login.php">Login</a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-copy">
                <div class="eyebrow">Textiles. Stock. Sales.</div>
                <h1>Galadawa Textiles</h1>
                <p class="lead">A clean operating system for textile inventory, point of sale, held orders, exchanges, and staff payouts.</p>
                <div class="actions">
                    <a class="btn btn-primary" href="login.php">Open System</a>
                    <a class="btn btn-light" href="#collections">View Products</a>
                </div>
            </div>
            <div class="hero-gallery" aria-label="Featured Galadawa products">
                <div class="photo large">
                    <img src="uploads/prod_14_6973de648055c.jpg" alt="Traditional cap">
                </div>
                <div class="photo-stack">
                    <div class="photo">
                        <img src="uploads/prod_1_6a1251fb4c84f.jpg" alt="Embellished fabric">
                    </div>
                    <div class="photo">
                        <img src="uploads/prod_19_6a0483e75f1c4.jpg" alt="Gold textile material">
                    </div>
                </div>
            </div>
        </section>

        <section class="stat-row" aria-label="System highlights">
            <div class="stat"><strong>POS</strong><span>Fast checkout and receipts.</span></div>
            <div class="stat"><strong>Stock</strong><span>Product photos, colors, and quantities.</span></div>
            <div class="stat"><strong>Admin</strong><span>Sales history, users, and payouts.</span></div>
        </section>

        <section class="section" id="collections">
            <div class="section-head">
                <h2>Product visuals built for quick selling.</h2>
                <p>Identify fabric and cap variants without slowing the sales desk.</p>
            </div>
            <div class="collection-grid">
                <article class="collection tall">
                    <img src="uploads/prod_1_6a1251fb4c84f.jpg" alt="Embellished lace fabric">
                    <div class="label"><strong>Fabrics</strong><span>Premium textile stock</span></div>
                </article>
                <article class="collection">
                    <img src="uploads/prod_11_6973dc3a588af.jpg" alt="Traditional cap design">
                    <div class="label"><strong>Caps</strong><span>Variant photo tracking</span></div>
                </article>
                <article class="collection tall">
                    <img src="uploads/prod_19_6a0483e75f1c4.jpg" alt="Gold fabric">
                    <div class="label"><strong>Colors</strong><span>Yard and color control</span></div>
                </article>
            </div>
        </section>

        <section class="section features" id="features">
            <div class="section-head">
                <h2>Everything needed at the counter.</h2>
                <p>Focused tools for the real daily workflow.</p>
            </div>
            <div class="feature-grid">
                <article class="feature"><span>01</span><h3>Sales</h3><p>Record transactions and print receipts.</p></article>
                <article class="feature"><span>02</span><h3>Inventory</h3><p>Track stock, product images, and low items.</p></article>
                <article class="feature"><span>03</span><h3>Holds</h3><p>Reserve items and release expired orders.</p></article>
                <article class="feature"><span>04</span><h3>Payouts</h3><p>Manage commission requests and approvals.</p></article>
            </div>
        </section>

        <section class="cta">
            <h2>Continue store operations.</h2>
            <a class="btn" href="login.php">Login</a>
        </section>
    </main>

    <footer class="footer">
        <strong>Galadawa Textiles</strong>
        <span>Inventory, sales, exchanges, holds, and payouts.</span>
    </footer>
</body>
</html>
