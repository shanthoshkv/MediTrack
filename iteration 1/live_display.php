<?php require_once 'core.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Display | SmartRF Inventory</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root {
    --bg:#040810; --surface:#0d1320; --border:#1a2840;
    --text:#e8edf5; --sub:#4d6082; --green:#10b981; --red:#ef4444; --yellow:#f59e0b;
    --accent:#3b82f6; --accent2:#8b5cf6; --cyan:#06b6d4;
    --grad:linear-gradient(135deg,#3b82f6,#8b5cf6,#06b6d4);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Syne',sans-serif;background:var(--bg);color:var(--text);padding:20px;min-height:100vh}
body::before{content:'';position:fixed;top:-30%;right:-20%;width:60vw;height:120vh;background:radial-gradient(ellipse,rgba(139,92,246,0.04),transparent 60%);pointer-events:none}

.topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:22px}
.brand{font-size:22px;font-weight:800;background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.meta{font-size:11px;color:var(--sub);margin-top:3px;font-family:'DM Mono',monospace}
.live-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:50px;padding:6px 14px;font-size:11px;color:#6ee7b7;font-family:'DM Mono',monospace}
.dot{width:7px;height:7px;background:var(--green);border-radius:50%;box-shadow:0 0 10px rgba(16,185,129,0.6);animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.3}}

.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;position:relative;overflow:hidden}
.stat::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--grad)}
.stat .n{font-size:30px;font-weight:800;margin-top:8px;line-height:1}
.stat .l{font-size:10px;color:var(--sub);font-family:'DM Mono',monospace;letter-spacing:1px;text-transform:uppercase}

.panels{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:16px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.card-head{padding:13px 16px;border-bottom:1px solid var(--border);font-size:10px;font-weight:700;font-family:'DM Mono',monospace;text-transform:uppercase;letter-spacing:1.5px;color:#a78bfa;display:flex;justify-content:space-between;align-items:center}
.card-body{padding:14px 16px;max-height:400px;overflow-y:auto}
.row{display:flex;justify-content:space-between;align-items:flex-start;padding:9px 0;border-bottom:1px solid rgba(255,255,255,0.03)}
.row:last-child{border-bottom:none}
.row .name{font-size:13px;font-weight:700}
.row .info{font-size:11px;color:var(--sub);margin-top:2px;font-family:'DM Mono',monospace}
.row .right{text-align:right;flex-shrink:0;padding-left:10px}
.pill{display:inline-flex;padding:3px 9px;border-radius:50px;font-size:11px;font-weight:700;border:1px solid;font-family:'DM Mono',monospace}
.g{background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.3);color:#6ee7b7}
.b{background:rgba(59,130,246,0.1);border-color:rgba(59,130,246,0.3);color:#93c5fd}
.r{background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.3);color:#fca5a5}
.y{background:rgba(245,158,11,0.1);border-color:rgba(245,158,11,0.3);color:#fcd34d}
.p{background:rgba(139,92,246,0.1);border-color:rgba(139,92,246,0.3);color:#c4b5fd}
.gr{background:rgba(100,116,139,0.1);border-color:rgba(100,116,139,0.3);color:#94a3b8}
.empty{text-align:center;color:var(--sub);padding:28px;font-size:13px}
.alert-bar{background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.2);border-radius:10px;padding:11px 14px;margin-bottom:14px;color:#fecaca;font-size:12px;display:none}
.admin-btn{background:rgba(139,92,246,0.1);border:1px solid rgba(139,92,246,0.2);color:#c4b5fd;border-radius:8px;padding:7px 14px;text-decoration:none;font-size:11px;font-family:'DM Mono',monospace;display:inline-flex;gap:6px;align-items:center}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:var(--sub);border-radius:4px}
@media(max-width:600px){.stat .n{font-size:22px}body{padding:12px}.brand{font-size:18px}}
</style>
</head>
<body>
<div class="topbar">
    <div>
        <div class="brand"><i class="fas fa-satellite-dish"></i> SmartRF Inventory</div>
        <div class="meta">Real-time RFID Asset Monitoring</div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <span id="clock" style="font-size:13px;color:var(--sub);font-family:'DM Mono',monospace">--:--:--</span>
        <div class="live-badge"><span class="dot"></span>Live Monitoring</div>
        <a href="login.php" class="admin-btn"><i class="fas fa-lock"></i>Admin</a>
    </div>
</div>

<div id="alertBar" class="alert-bar"></div>

<div class="stats">
    <div class="stat"><div class="l">Total Items</div><div class="n" id="s-total">—</div></div>
    <div class="stat"><div class="l">In Stock</div><div class="n" id="s-stock" style="color:#6ee7b7">—</div></div>
    <div class="stat"><div class="l">Checked Out</div><div class="n" id="s-co" style="color:#93c5fd">—</div></div>
    <div class="stat"><div class="l">Scans Today</div><div class="n" id="s-today" style="color:#c4b5fd">—</div></div>
    <div class="stat"><div class="l">Unknown Tags</div><div class="n" id="s-unknown" style="color:#fca5a5">—</div></div>
    <div class="stat"><div class="l">Open Alerts</div><div class="n" id="s-alerts" style="color:#fcd34d">—</div></div>
</div>

<div class="panels">
    <div class="card">
        <div class="card-head"><span><i class="fas fa-location-dot" style="margin-right:8px"></i>Items in Active Zones</span><span id="zone-count" style="color:var(--sub)">0</span></div>
        <div class="card-body" id="zoneItems"><div class="empty"><i class="fas fa-spinner fa-spin"></i></div></div>
    </div>
    <div class="card">
        <div class="card-head"><span><i class="fas fa-rss" style="margin-right:8px"></i>Live Scan Feed</span><span style="color:var(--sub)">Latest 20</span></div>
        <div class="card-body" id="liveFeed"><div class="empty"><i class="fas fa-spinner fa-spin"></i></div></div>
    </div>
</div>

<div class="panels">
    <div class="card">
        <div class="card-head"><i class="fas fa-network-wired" style="margin-right:8px"></i>Reader Network Health</div>
        <div class="card-body" id="readerList"><div class="empty"><i class="fas fa-spinner fa-spin"></i></div></div>
    </div>
    <div class="card">
        <div class="card-head"><i class="fas fa-triangle-exclamation" style="margin-right:8px"></i>System Info</div>
        <div class="card-body">
            <div class="row"><div><div class="name">Expiring Items</div><div class="info">Lifecycle: expiring_soon</div></div><div class="right"><span class="pill y" id="info-expiring">—</span></div></div>
            <div class="row"><div><div class="name">Expired Items</div><div class="info">Lifecycle: expired</div></div><div class="right"><span class="pill r" id="info-expired">—</span></div></div>
            <div class="row"><div><div class="name">Missing Assets</div><div class="info">Status: missing</div></div><div class="right"><span class="pill r" id="info-missing">—</span></div></div>
            <div class="row"><div><div class="name">Offline Readers</div><div class="info">Heartbeat &gt; 2 min ago</div></div><div class="right"><span class="pill r" id="info-offline">—</span></div></div>
            <div class="row"><div><div class="name">Last Refreshed</div><div class="info">Auto-refresh every 5s</div></div><div class="right" id="info-refreshed" style="font-size:11px;color:var(--sub)">—</div></div>
        </div>
    </div>
</div>

<script>
const esc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
function ago(ts) {
    if(!ts) return 'Never';
    const d = Math.floor((Date.now() - new Date(ts.replace(' ','T')).getTime())/1000);
    if(isNaN(d)||d<0) return ts;
    if(d<60) return d+'s ago';
    if(d<3600) return Math.floor(d/60)+'m ago';
    if(d<86400) return Math.floor(d/3600)+'h ago';
    return Math.floor(d/86400)+'d ago';
}
function actionPill(a) {
    const m={scanned:'gr',return:'g',transfer:'b',checkout:'b',conflict_suppressed:'r',manual_move:'p',cycle_count:'y'};
    return `<span class="pill ${m[a]||'gr'}">${esc(a)}</span>`;
}
function updateClock(){document.getElementById('clock').textContent=new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit'});}
async function fetchData(){
    try{
        const r=await fetch('get_live_data.php',{cache:'no-store'});
        const d=await r.json();
        document.getElementById('s-total').textContent=d.total_items;
        document.getElementById('s-stock').textContent=d.in_stock;
        document.getElementById('s-co').textContent=d.checked_out;
        document.getElementById('s-today').textContent=d.today_scans;
        document.getElementById('s-unknown').textContent=d.unknown_today;
        document.getElementById('s-alerts').textContent=d.open_alerts;
        document.getElementById('info-expiring').textContent=d.expiring_soon;
        document.getElementById('info-expired').textContent=d.expired;
        document.getElementById('info-missing').textContent=d.missing||0;
        document.getElementById('info-offline').textContent=d.offline_readers;
        document.getElementById('info-refreshed').textContent=d.server_time;
        const ab=document.getElementById('alertBar');
        const parts=[];
        if(d.unknown_today>0) parts.push(`<strong>${d.unknown_today}</strong> unknown tag(s) today`);
        if(d.offline_readers>0) parts.push(`<strong>${d.offline_readers}</strong> reader(s) offline`);
        if(d.open_alerts>0) parts.push(`<strong>${d.open_alerts}</strong> unresolved alert(s)`);
        if(parts.length){ab.style.display='block';ab.innerHTML='<i class="fas fa-triangle-exclamation" style="margin-right:8px"></i>'+parts.join(' &bull; ')+' — review admin panel.';}
        else{ab.style.display='none';}
        const zi=document.getElementById('zoneItems');
        document.getElementById('zone-count').textContent=(d.zone_items?.length??0)+' items';
        zi.innerHTML=d.zone_items?.length?d.zone_items.map(it=>`
            <div class="row">
                <div><div class="name">${esc(it.item_name)}</div><div class="info">${esc(it.location_name??'Unknown')}</div></div>
                <div class="right">
                    <div>${it.status==='in_stock'?'<span class="pill g">in_stock</span>':'<span class="pill b">'+esc(it.status)+'</span>'}</div>
                    <div style="font-size:11px;color:var(--sub);margin-top:3px">${ago(it.last_seen)}</div>
                </div>
            </div>`).join(''):'<div class="empty">No active items.</div>';
        const lf=document.getElementById('liveFeed');
        lf.innerHTML=d.logs?.length?d.logs.map(lg=>`
            <div class="row">
                <div><div class="name">${esc(lg.item_name??'Unknown Tag')}</div><div class="info">${esc(lg.location_name??lg.reader_id)}</div></div>
                <div class="right">
                    <div>${actionPill(lg.action_type)}</div>
                    <div style="font-size:11px;color:var(--sub);margin-top:3px">${ago(lg.scan_time)}</div>
                </div>
            </div>`).join(''):'<div class="empty">No scan events yet.</div>';
        const rl=document.getElementById('readerList');
        rl.innerHTML=d.readers?.length?d.readers.map(rd=>`
            <div class="row">
                <div><div class="name">${esc(rd.reader_id)}</div><div class="info">${esc(rd.location_name)} &bull; ${esc(rd.zone)}</div></div>
                <div class="right">
                    <div>${rd.is_offline?'<span class="pill r">offline</span>':'<span class="pill g">online</span>'}</div>
                    <div style="font-size:11px;color:var(--sub);margin-top:3px">${rd.last_heartbeat?ago(rd.last_heartbeat):'Never'}</div>
                </div>
            </div>`).join(''):'<div class="empty">No readers configured.</div>';
    }catch(e){
        const ab=document.getElementById('alertBar');
        ab.style.display='block';
        ab.innerHTML='<i class="fas fa-wifi-slash" style="margin-right:8px"></i>Feed temporarily unavailable — retrying...';
    }
}
updateClock(); fetchData();
setInterval(updateClock,1000); setInterval(fetchData,5000);
</script>
</body>
</html>
