<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galadawa Textiles</title>
    <style>
        :root {
            --ink: #172033;
            --muted: #647084;
            --line: #dfe5ec;
            --brand: #1e3c72;
            --brand-2: #2a5298;
            --gold: #c59b3d;
            --paper: #f7f8fb;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background: var(--paper);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .landing-page {
            min-height: 100vh;
            overflow-x: hidden;
        }

        .site-header {
            position: fixed;
            inset: 0 0 auto 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px clamp(20px, 5vw, 72px);
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(223, 229, 236, 0.85);
            backdrop-filter: blur(16px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand img {
            width: 46px;
            height: 46px;
            object-fit: contain;
        }

        .brand-name {
            display: block;
            font-size: clamp(16px, 2vw, 20px);
            font-weight: 800;
            line-height: 1.05;
            color: var(--brand);
        }

        .brand-subtitle {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .nav-link {
            color: var(--muted);
            font-size: 14px;
            font-weight: 700;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 14px;
            border: 1px solid transparent;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            color: var(--white);
            background: var(--brand);
            box-shadow: 0 10px 24px rgba(30, 60, 114, 0.22);
        }

        .btn-primary:hover {
            background: var(--brand-2);
        }

        .btn-secondary {
            color: var(--brand);
            background: var(--white);
            border-color: var(--line);
        }

        .hero {
            min-height: 92vh;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, 0.86fr);
            align-items: stretch;
            padding: 92px clamp(20px, 5vw, 72px) 42px;
            background:
                linear-gradient(90deg, rgba(247, 248, 251, 0.98) 0%, rgba(247, 248, 251, 0.94) 48%, rgba(247, 248, 251, 0.5) 100%),
                url("uploads/prod_1_6a1251fb4c84f.jpg") center right / cover no-repeat;
        }

        .hero-copy {
            align-self: center;
            max-width: 720px;
            padding: clamp(36px, 7vw, 84px) 0;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            color: var(--brand);
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 34px;
            height: 2px;
            background: var(--gold);
        }

        h1 {
            max-width: 680px;
            color: var(--ink);
            font-size: clamp(44px, 7vw, 84px);
            line-height: 0.98;
            letter-spacing: 0;
            font-weight: 900;
        }

        .hero-copy p {
            max-width: 570px;
            margin-top: 24px;
            color: var(--muted);
            font-size: clamp(16px, 2vw, 20px);
            line-height: 1.7;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 34px;
        }

        .hero-media {
            align-self: end;
            display: grid;
            grid-template-columns: 1fr 0.72fr;
            gap: 16px;
            min-height: 520px;
            padding: 34px 0 14px;
        }

        .hero-photo {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            background: #d8dee6;
            box-shadow: 0 24px 50px rgba(23, 32, 51, 0.18);
        }

        .hero-photo img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .hero-photo.large {
            min-height: 520px;
        }

        .hero-stack {
            display: grid;
            gap: 16px;
        }

        .hero-stack .hero-photo {
            min-height: 252px;
        }

        .metric-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1px;
            background: var(--line);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .metric {
            min-height: 132px;
            padding: 30px clamp(20px, 4vw, 72px);
            background: var(--white);
        }

        .metric strong {
            display: block;
            color: var(--brand);
            font-size: clamp(26px, 3vw, 38px);
            line-height: 1;
        }

        .metric span {
            display: block;
            margin-top: 10px;
            color: var(--muted);
            font-weight: 700;
            line-height: 1.45;
        }

        .section {
            padding: clamp(54px, 8vw, 96px) clamp(20px, 5vw, 72px);
        }

        .section-heading {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 28px;
            margin-bottom: 30px;
        }

        .section-heading h2 {
            max-width: 560px;
            font-size: clamp(30px, 4vw, 48px);
            line-height: 1.08;
            letter-spacing: 0;
        }

        .section-heading p {
            max-width: 470px;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .collections {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr 1fr;
            gap: 18px;
        }

        .collection {
            min-height: 360px;
            overflow: hidden;
            border-radius: 8px;
            position: relative;
            background: #d8dee6;
        }

        .collection.tall {
            min-height: 520px;
        }

        .collection img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .collection:hover img {
            transform: scale(1.04);
        }

        .collection-label {
            position: absolute;
            left: 18px;
            bottom: 18px;
            right: 18px;
            padding: 14px 16px;
            border-radius: 8px;
            color: var(--white);
            background: rgba(23, 32, 51, 0.76);
            backdrop-filter: blur(12px);
        }

        .collection-label strong {
            display: block;
            font-size: 18px;
        }

        .collection-label span {
            display: block;
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.78);
            font-size: 13px;
            font-weight: 700;
        }

        .operations {
            background: var(--white);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--line);
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
        }

        .feature {
            min-height: 190px;
            padding: 24px;
            background: var(--white);
        }

        .feature-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            margin-bottom: 26px;
            border-radius: 8px;
            color: var(--white);
            background: var(--brand);
            font-size: 13px;
            font-weight: 900;
        }

        .feature h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }

        .feature p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.65;
        }

        .final-cta {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 28px;
            color: var(--white);
            background: var(--brand);
            padding: clamp(34px, 6vw, 62px) clamp(20px, 5vw, 72px);
        }

        .final-cta h2 {
            max-width: 780px;
            font-size: clamp(30px, 4vw, 48px);
            line-height: 1.1;
        }

        .final-cta p {
            max-width: 640px;
            margin-top: 14px;
            color: rgba(255, 255, 255, 0.78);
            line-height: 1.7;
        }

        .final-cta .btn-primary {
            color: var(--brand);
            background: var(--white);
            box-shadow: none;
        }

        .site-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 24px clamp(20px, 5vw, 72px);
            color: var(--muted);
            background: var(--white);
            font-size: 13px;
            font-weight: 700;
        }

        @media (max-width: 1040px) {
            .hero {
                grid-template-columns: 1fr;
                min-height: auto;
                background:
                    linear-gradient(180deg, rgba(247, 248, 251, 0.98) 0%, rgba(247, 248, 251, 0.9) 100%),
                    url("uploads/prod_1_6a1251fb4c84f.jpg") center / cover no-repeat;
            }

            .hero-media {
                min-height: 420px;
                padding-top: 0;
            }

            .hero-photo.large {
                min-height: 420px;
            }

            .feature-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .site-header {
                position: static;
                padding: 14px 18px;
            }

            .brand-subtitle,
            .nav-link {
                display: none;
            }

            .brand img {
                width: 40px;
                height: 40px;
            }

            .hero {
                padding: 28px 18px 28px;
            }

            .hero-copy {
                padding: 28px 0 22px;
            }

            h1 {
                font-size: clamp(38px, 15vw, 58px);
            }

            .hero-actions {
                flex-direction: column;
            }

            .hero-actions .btn,
            .header-actions .btn {
                width: 100%;
            }

            .hero-media {
                grid-template-columns: 1fr;
                min-height: 0;
            }

            .hero-photo.large,
            .hero-stack .hero-photo {
                min-height: 280px;
            }

            .hero-stack {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .metric-strip,
            .collections,
            .feature-grid,
            .final-cta {
                grid-template-columns: 1fr;
            }

            .collection,
            .collection.tall {
                min-height: 320px;
            }

            .section-heading {
                display: block;
            }

            .section-heading p {
                margin-top: 14px;
            }

            .site-footer {
                display: block;
            }

            .site-footer span {
                display: block;
                margin-top: 8px;
            }
        }

        @media (max-width: 460px) {
            .site-header {
                align-items: flex-start;
            }

            .header-actions {
                width: 45%;
            }

            .brand {
                width: 55%;
            }

            .hero-stack {
                grid-template-columns: 1fr;
            }

            .hero-photo.large,
            .hero-stack .hero-photo,
            .collection,
            .collection.tall {
                min-height: 250px;
            }
        }
    </style>
</head>
<body class="landing-page">
    <header class="site-header">
        <a class="brand" href="index.php" aria-label="Galadawa Textiles home">
            <img src="img/logo.png" alt="Galadawa Textiles logo">
            <span>
                <span class="brand-name">Galadawa Textiles</span>
                <span class="brand-subtitle">Inventory and sales management</span>
            </span>
        </a>
        <nav class="header-actions" aria-label="Primary">
            <a class="nav-link" href="#collections">Collections</a>
            <a class="nav-link" href="#operations">Operations</a>
            <a class="btn btn-primary" href="login.php">Login</a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-copy">
                <div class="eyebrow">Premium textiles, controlled stock</div>
                <h1>Galadawa Textiles</h1>
                <p>Manage fabric inventory, sales activity, product photos, held orders, exchanges, payouts, and low-stock decisions from one focused store system.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="login.php">Open Store System</a>
                    <a class="btn btn-secondary" href="#collections">View Collections</a>
                </div>
            </div>

            <div class="hero-media" aria-label="Featured products">
                <div class="hero-photo large">
                    <img src="uploads/prod_14_6973de648055c.jpg" alt="Patterned traditional cap">
                </div>
                <div class="hero-stack">
                    <div class="hero-photo">
                        <img src="uploads/prod_1_6a1251fb4c84f.jpg" alt="Embellished fabric">
                    </div>
                    <div class="hero-photo">
                        <img src="uploads/prod_19_6a0483e75f1c4.jpg" alt="Gold fabric sample">
                    </div>
                </div>
            </div>
        </section>

        <section class="metric-strip" aria-label="Store highlights">
            <div class="metric">
                <strong>POS</strong>
                <span>Fast sales recording with receipts and customer history.</span>
            </div>
            <div class="metric">
                <strong>Stock</strong>
                <span>Photo-led product records with color and quantity tracking.</span>
            </div>
            <div class="metric">
                <strong>Teams</strong>
                <span>Admin and sales attendant workflows in one place.</span>
            </div>
        </section>

        <section class="section" id="collections">
            <div class="section-heading">
                <h2>Textiles and caps presented with clear product visuals.</h2>
                <p>Use rich product images to identify stock quickly and keep sales decisions tied to the actual item on hand.</p>
            </div>
            <div class="collections">
                <article class="collection tall">
                    <img src="uploads/prod_1_6a1251fb4c84f.jpg" alt="Embellished lace fabric">
                    <div class="collection-label">
                        <strong>Embellished Fabrics</strong>
                        <span>Premium fabric catalog</span>
                    </div>
                </article>
                <article class="collection">
                    <img src="uploads/prod_11_6973dc3a588af.jpg" alt="Traditional cap">
                    <div class="collection-label">
                        <strong>Traditional Caps</strong>
                        <span>Photo-based stock records</span>
                    </div>
                </article>
                <article class="collection tall">
                    <img src="uploads/prod_19_6a0483e75f1c4.jpg" alt="Gold fabric material">
                    <div class="collection-label">
                        <strong>Fabric Inventory</strong>
                        <span>Quantity and color tracking</span>
                    </div>
                </article>
            </div>
        </section>

        <section class="section operations" id="operations">
            <div class="section-heading">
                <h2>Built for daily shop operations.</h2>
                <p>The system keeps routine work close together, from checkout to inventory updates and staff payouts.</p>
            </div>
            <div class="feature-grid">
                <article class="feature">
                    <span class="feature-number">01</span>
                    <h3>Sales Desk</h3>
                    <p>Record purchases, print receipts, and keep every sale tied to the attendant who handled it.</p>
                </article>
                <article class="feature">
                    <span class="feature-number">02</span>
                    <h3>Inventory Control</h3>
                    <p>Monitor quantities, product images, low-stock items, and restock priorities from the dashboard.</p>
                </article>
                <article class="feature">
                    <span class="feature-number">03</span>
                    <h3>Held Orders</h3>
                    <p>Reserve products for customers, release expired holds, and complete orders when payment is ready.</p>
                </article>
                <article class="feature">
                    <span class="feature-number">04</span>
                    <h3>Payout Requests</h3>
                    <p>Track staff commission balances, payout approvals, and request history with admin visibility.</p>
                </article>
            </div>
        </section>

        <section class="final-cta">
            <div>
                <h2>Open the management system and continue store operations.</h2>
                <p>Admins and sales attendants can sign in with their existing accounts.</p>
            </div>
            <a class="btn btn-primary" href="login.php">Login</a>
        </section>
    </main>

    <footer class="site-footer">
        <strong>Galadawa Textiles</strong>
        <span>Inventory, sales, exchanges, holds, and payouts.</span>
    </footer>
</body>
</html>
