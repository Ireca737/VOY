<?php
/**
 * VOY Flight Test Monitor FT-01 — diagnostic, read-only.
 * Based on booktest.php. Upload in the same public_html directory.
 */
use Proxy\Api\Api;
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

if (empty($_SESSION['pilotid']) || (int)$_SESSION['pilotid'] !== 14240) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Sessione pilota non autorizzata.');
}

if (isset($_GET['data'])) {
    header('Content-Type: application/json; charset=utf-8');
    Api::__constructStatic();
    $result = ['timestamp_utc' => gmdate('c'), 'sources' => []];
    foreach ([
        'bids' => 'v1/bids',
        'acars' => 'v1/map/acars',
    ] as $name => $endpoint) {
        $started = microtime(true);
        try {
            $res = Api::sendSync('GET', $endpoint, null);
            $body = (string)$res->getBody();
            $decoded = json_decode($body, true);
            $result['sources'][$name] = [
                'http' => $res->getStatusCode(),
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
                'valid_json' => json_last_error() === JSON_ERROR_NONE,
                'data' => json_last_error() === JSON_ERROR_NONE ? $decoded : null,
                'raw' => json_last_error() === JSON_ERROR_NONE ? null : $body,
            ];
        } catch (Throwable $e) {
            error_log('VOY FT01 ' . $name . ': ' . $e->getMessage());
            $result['sources'][$name] = [
                'http' => null, 'duration_ms' => (int)round((microtime(true) - $started) * 1000),
                'error' => 'Errore API; consultare log PHP.'
            ];
        }
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>VOY · Flight Test Monitor FT-01</title>
<style>
:root{color-scheme:dark;font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#101820;color:#edf3f8}
*{box-sizing:border-box}body{margin:0;padding:20px}header{display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:center}
h1{font-size:1.35rem;margin:0}h2{font-size:1rem;margin:0}small,.muted{color:#a8bac9}
button{background:#23445c;border:1px solid #46677c;border-radius:6px;color:white;padding:9px 13px;cursor:pointer}
button:hover{background:#305b79}button:disabled{opacity:.5;cursor:default}
.toolbar{display:flex;gap:8px;flex-wrap:wrap}.stats{display:flex;gap:14px;flex-wrap:wrap;margin:18px 0}
.stat{background:#1b2a36;padding:10px 14px;border-radius:7px}.stat b{display:block;margin-top:3px}
.layout{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.panel{background:#1b2a36;border:1px solid #344a5a;border-radius:9px;padding:14px;min-width:0}
.panelhead{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.ok{color:#68dfa6}.bad{color:#ff9a91}.changed{color:#ffd478}
pre{font:12px/1.5 Consolas,Monaco,monospace;background:#0e1821;border-radius:6px;padding:12px;white-space:pre-wrap;overflow-wrap:anywhere;max-height:62vh;overflow:auto;margin:0}
details{margin-top:12px}summary{cursor:pointer;color:#b7d9f4}
table{width:100%;border-collapse:collapse;font:12px/1.5 Consolas,monospace;table-layout:fixed}
td,th{padding:5px;border-bottom:1px solid #314451;overflow-wrap:anywhere;text-align:left;vertical-align:top}
td:first-child{width:36%;color:#a9d3ef}td:last-child{width:64%}
tr.flash{background:#4c432b}
#events{max-height:310px;overflow:auto}#events div{border-bottom:1px solid #344a5a;padding:7px 0;font:12px Consolas,monospace;overflow-wrap:anywhere}
footer{margin-top:18px;color:#a8bac9;font-size:.85rem}
@media(max-width:850px){.layout{grid-template-columns:1fr}}
</style>
</head>
<body>
<header><div><h1>VOY · FLIGHT TEST MONITOR FT-01</h1><small>Solo diagnostica · Pilota 14240 · GET v1/bids + GET v1/map/acars</small></div>
<div class="toolbar"><button id="toggle">Pausa</button><button id="now">Aggiorna ora</button><button id="save">Esporta snapshot</button><button id="logsave">Esporta registro</button><button id="clear">Svuota registro</button></div></header>
<div class="stats">
<div class="stat"><small>Ultimo campionamento UTC</small><b id="clock">In attesa</b></div>
<div class="stat"><small>Intervallo</small><b>5 secondi</b></div>
<div class="stat"><small>Acquisizioni</small><b id="count">0</b></div>
<div class="stat"><small>Stato monitor</small><b id="state">Avvio</b></div>
</div>
<div class="layout">
<section class="panel"><div class="panelhead"><h2>01 · VABASE BIDS</h2><span id="bidsStatus" class="muted">—</span></div>
<div id="bidsFields"></div><details open><summary>JSON completo</summary><pre id="bidsRaw">In attesa…</pre></details></section>
<section class="panel"><div class="panelhead"><h2>02 · ACARS LIVE</h2><span id="acarsStatus" class="muted">—</span></div>
<div id="acarsFields"></div><details open><summary>JSON completo</summary><pre id="acarsRaw">In attesa…</pre></details></section>
</div>
<section class="panel" style="margin-top:14px"><div class="panelhead"><h2>Registro cambiamenti (solo browser)</h2><small id="logcount">0 eventi</small></div><div id="events"></div></section>
<footer>Le due fonti sono lette in sequenza, senza richieste sovrapposte. Il monitor non modifica BID, ACARS o database. Le esportazioni possono contenere dati personali: conservarle in luogo riservato. Il registro rimane in memoria finché la pagina è aperta.</footer>
<script>
'use strict';
const ENDPOINT = location.pathname + '?data=1';
const INTERVAL = 5000, MAX_EVENTS = 1500;
let running = true, busy = false, samples = 0, last = null, events = [];
const el = id => document.getElementById(id);
const fmt = value => JSON.stringify(value, null, 2);
const scalar = value => value === null ? 'null' : typeof value === 'object' ? JSON.stringify(value) : String(value);
function download(name, obj) {
  const blob = new Blob([JSON.stringify(obj,null,2)], {type:'application/json'});
  const url = URL.createObjectURL(blob), a = document.createElement('a');
  a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();
  setTimeout(()=>URL.revokeObjectURL(url),1000);
}
function flattened(value, prefix='', out={}) {
  if (value && typeof value==='object') {
    const keys = Object.keys(value);
    if (!keys.length) out[prefix || '(root)'] = Array.isArray(value)?'[]':'{}';
    for (const key of keys) flattened(value[key], prefix ? prefix+'.'+key : key, out);
  } else out[prefix || '(root)'] = scalar(value);
  return out;
}
function appendEvent(msg, utc) {
  events.push({utc, event:msg}); if(events.length>MAX_EVENTS) events.shift();
}
function renderEvents() {
  const root=el('events');root.replaceChildren();
  for(const e of events.slice(-100).reverse()) {
    const line=document.createElement('div');line.textContent=e.utc+' · '+e.event;root.appendChild(line);
  }
  el('logcount').textContent=events.length+' eventi (ultimi 100 visualizzati)';
}
function renderSource(name, source, previous) {
  const status=el(name+'Status'), fields=el(name+'Fields'), raw=el(name+'Raw');
  const ok=source && source.http===200 && source.valid_json;
  status.textContent=source ? 'HTTP '+(source.http??'ERR')+' · '+source.duration_ms+' ms' : 'Nessuna risposta';
  status.className=ok?'ok':'bad';
  raw.textContent=source ? fmt(source) : 'Nessuna risposta';
  fields.replaceChildren();
  const current=flatten(source?.data ?? null), old=flatten(previous?.data ?? null);
  const table=document.createElement('table'), tbody=document.createElement('tbody');
  for(const [key,val] of Object.entries(current)) {
    const tr=document.createElement('tr');
    if(previous && old[key]!==val) tr.className='flash';
    const k=document.createElement('td'), v=document.createElement('td');
    k.textContent=key;v.textContent=val;tr.append(k,v);tbody.appendChild(tr);
  }
  table.appendChild(tbody);fields.appendChild(table);
}
async function sample() {
  if(busy) return;
  busy=true;el('state').textContent='Lettura…';
  try {
    const response=await fetch(ENDPOINT,{credentials:'same-origin',cache:'no-store'});
    if(!response.ok) throw Error('HTTP '+response.status);
    const data=await response.json();
    const stamp=data.timestamp_utc || new Date().toISOString();
    for(const name of ['bids','acars']) {
      const src=data.sources?.[name], prev=last?.sources?.[name];
      renderSource(name,src,prev);
      if(last) {
        const before=flatten(prev??{}), after=flatten(src??{});
        for(const key of new Set([...Object.keys(before),...Object.keys(after)])) {
          if(before[key]!==after[key]) appendEvent(name+'.'+key+': '+(before[key]??'[assente]')+' → '+(after[key]??'[assente]'),stamp);
        }
      } else appendEvent(name+': prima acquisizione',stamp);
    }
    last=data;samples++;el('clock').textContent=stamp;el('count').textContent=samples;
    renderEvents();el('state').textContent=running?'Attivo':'In pausa';
  } catch(err) {
    appendEvent('Errore acquisizione: '+err.message,new Date().toISOString());
    renderEvents();el('state').textContent='Errore: '+err.message;
  } finally {busy=false;}
}
el('toggle').onclick=()=>{running=!running;el('toggle').textContent=running?'Pausa':'Riprendi';el('state').textContent=running?'Attivo':'In pausa';if(running) sample();};
el('now').onclick=sample;
el('save').onclick=()=>{if(last)download('voy-ft01-snapshot-'+Date.now()+'.json',last);};
el('logsave').onclick=()=>download('voy-ft01-events-'+Date.now()+'.json',events);
el('clear').onclick=()=>{events=[];renderEvents();};
sample();
setInterval(()=>{if(running&&!busy)sample();},INTERVAL);
</script></body></html>
