<style>
    :root {
        --bg: #f2f4f7;
        --paper: #ffffff;
        --text: #1c1e21;
        --muted: #5f6368;
        --border: #dadce0;
        --focus: #1967d2;
        --danger-bg: #fce8e8;
        --danger-border: #f0b4b4;
        --danger-text: #8b1a1a;
        --ok-bg: #e8f5e9;
        --ok-border: #a5d6a7;
        --ok-text: #1b5e20;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 15px;
        line-height: 1.45;
        color: var(--text);
        background: var(--bg);
    }
    .sans { font-family: "Segoe UI", system-ui, -apple-system, sans-serif; }
    .page { max-width: 960px; margin: 0 auto; padding: 24px 18px 56px; }

    .site-nav {
        background: var(--paper);
        border-bottom: 1px solid var(--border);
        margin-bottom: 0;
    }
    .site-nav-inner {
        max-width: 960px;
        margin: 0 auto;
        padding: 0 18px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px 16px;
        min-height: 52px;
    }
    .site-nav-brand {
        font-family: "Segoe UI", system-ui, sans-serif;
        font-weight: 700;
        font-size: 1.05rem;
        letter-spacing: -0.02em;
    }
    .site-nav-brand a {
        color: var(--text);
        text-decoration: none;
    }
    .site-nav-brand a:hover { text-decoration: underline; }
    .site-nav-links {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 20px;
        font-family: "Segoe UI", system-ui, sans-serif;
        font-size: 0.9rem;
    }
    .site-nav-links a {
        color: var(--muted);
        text-decoration: none;
        padding: 8px 0;
        border-bottom: 2px solid transparent;
    }
    .site-nav-links a:hover { color: var(--focus); }
    .site-nav-links a.nav-active {
        color: var(--focus);
        font-weight: 600;
        border-bottom-color: var(--focus);
    }
    .site-nav-user {
        font-size: 0.8rem;
        color: var(--muted);
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .logout {
        font-family: "Segoe UI", system-ui, sans-serif;
        font-size: 0.875rem;
        color: var(--focus);
        text-decoration: none;
    }
    .logout:hover { text-decoration: underline; }
    h1 {
        font-family: "Segoe UI", system-ui, sans-serif;
        font-size: 1.5rem;
        font-weight: 600;
        margin: 28px 0 6px;
        letter-spacing: -0.02em;
    }
    .lead { margin: 0 0 20px; color: var(--muted); max-width: 48em; }
    .stats {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 24px;
    }
    .stat {
        font-family: "Segoe UI", system-ui, sans-serif;
        background: var(--paper);
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 12px 16px;
        min-width: 120px;
    }
    .stat .num { font-size: 1.35rem; font-weight: 600; }
    .stat .lbl { font-size: 0.8rem; color: var(--muted); margin-top: 2px; }
    .flash {
        font-family: "Segoe UI", system-ui, sans-serif;
        padding: 10px 12px;
        border-radius: 4px;
        margin-bottom: 16px;
        font-size: 0.9rem;
    }
    .flash-err { background: var(--danger-bg); border: 1px solid var(--danger-border); color: var(--danger-text); }
    .flash-ok { background: var(--ok-bg); border: 1px solid var(--ok-border); color: var(--ok-text); }
    .card {
        font-family: "Segoe UI", system-ui, sans-serif;
        background: var(--paper);
        border: 1px solid var(--border);
        border-radius: 4px;
        padding: 20px 22px;
        margin-bottom: 16px;
    }
    .card h2 {
        font-family: Georgia, serif;
        font-size: 1.05rem;
        font-weight: normal;
        margin: 0 0 14px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border);
        color: var(--text);
    }
    label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--muted); margin-bottom: 5px; }
    input, select, textarea {
        width: 100%;
        padding: 9px 11px;
        border: 1px solid var(--border);
        border-radius: 4px;
        background: #fff;
        color: var(--text);
        font-family: inherit;
        font-size: 0.95rem;
    }
    textarea { min-height: 88px; resize: vertical; }
    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--focus);
        box-shadow: 0 0 0 2px rgba(25, 103, 210, 0.12);
    }
    .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 560px) { .row2 { grid-template-columns: 1fr; } }
    .field { margin-bottom: 12px; }
    .hint { font-size: 0.8rem; color: var(--muted); margin: 6px 0 0; }
    .btn-submit {
        margin-top: 6px;
        padding: 10px 18px;
        border: 1px solid #1558b0;
        border-radius: 4px;
        background: var(--focus);
        color: #fff;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        font-family: inherit;
    }
    .btn-submit:hover { background: #1558b0; }
    .grid-split { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 800px) { .grid-split { grid-template-columns: 1fr; } }
    table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--border); vertical-align: top; }
    th { font-weight: 600; color: var(--muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.03em; }
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 0.72rem;
        font-weight: 600;
        border: 1px solid var(--border);
        background: #f8f9fa;
        color: var(--text);
    }
    .badge-done { border-color: var(--ok-border); background: var(--ok-bg); color: var(--ok-text); }
    .badge-warn { border-color: #ffe082; background: #fff8e1; color: #6d4c41; }
    .empty { color: var(--muted); margin: 0; font-size: 0.92rem; }
    .car-list { list-style: none; padding: 0; margin: 0; }
    .car-list li {
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 4px;
        margin-bottom: 8px;
        background: #fafafa;
    }
    .car-list li:last-child { margin-bottom: 0; }
    .car-title { font-weight: 600; color: var(--text); }
    .car-sub { font-size: 0.85rem; color: var(--muted); margin-top: 4px; }
    .staff { color: var(--muted); }
    .staff code { font-size: 0.85em; background: #eceff1; padding: 2px 6px; border-radius: 3px; }
</style>
