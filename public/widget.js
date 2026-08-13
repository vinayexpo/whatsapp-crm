(function(){var e=`omnichat_widget_visitor_id`;function t(){let e=document.currentScript;if(e)return e;let t=document.getElementsByTagName(`script`);return t[t.length-1]}function n(){let t=window.localStorage.getItem(e);return t||(t=crypto.randomUUID(),window.localStorage.setItem(e,t)),t}async function r(e,t,n,r){let i=await fetch(`https://mintcream-jellyfish-201700.hostingersite.com/api/widget${n}`,{...r,headers:{"Content-Type":`application/json`,"X-Widget-Key":e,"X-Visitor-Id":t,...r?.headers}});if(!i.ok)throw Error(`Widget request failed: ${i.status}`);return i.json()}function i(){let e=document.createElement(`style`);e.textContent=`
    .omnichat-widget-launcher {
      position: fixed; bottom: 20px; right: 20px; width: 56px; height: 56px;
      border-radius: 50%; background: #5B6EF5; color: #fff; border: none;
      cursor: pointer; box-shadow: 0 4px 16px rgba(0,0,0,0.2); font-size: 24px;
      z-index: 999999; display: flex; align-items: center; justify-content: center;
    }
    .omnichat-widget-panel {
      position: fixed; bottom: 88px; right: 20px; width: 340px; max-width: calc(100vw - 40px);
      height: 480px; max-height: calc(100vh - 120px); background: #fff; border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.25); z-index: 999999; display: none;
      flex-direction: column; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, sans-serif;
    }
    .omnichat-widget-panel.open { display: flex; }
    .omnichat-widget-header {
      background: #5B6EF5; color: #fff; padding: 14px 16px; font-weight: 600; font-size: 15px;
    }
    .omnichat-widget-messages {
      flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px;
    }
    .omnichat-widget-bubble {
      max-width: 80%; padding: 8px 12px; border-radius: 12px; font-size: 13px; line-height: 1.4;
      white-space: pre-wrap; word-break: break-word;
    }
    .omnichat-widget-bubble.contact { align-self: flex-end; background: #5B6EF5; color: #fff; }
    .omnichat-widget-bubble.bot { align-self: flex-start; background: #f0f1f5; color: #1a1a1a; }
    .omnichat-widget-input-row {
      display: flex; gap: 8px; padding: 10px; border-top: 1px solid #eee;
    }
    .omnichat-widget-input {
      flex: 1; border: 1px solid #ddd; border-radius: 8px; padding: 8px 10px; font-size: 13px; outline: none;
    }
    .omnichat-widget-send {
      background: #5B6EF5; color: #fff; border: none; border-radius: 8px; padding: 0 14px; cursor: pointer; font-size: 13px;
    }
  `,document.head.appendChild(e)}function a(e,t){let n=document.createElement(`div`);return n.className=`omnichat-widget-bubble ${t.direction===`inbound`?`contact`:`bot`}`,n.textContent=t.text,n.dataset.messageId=t.id,e.appendChild(n),e.scrollTop=e.scrollHeight,n}async function o(){let e=t().dataset.widgetKey;if(!e){console.error(`Creative Connects widget: missing data-widget-key attribute`);return}i();let o=n(),s=new Set,c=[],l=null,u=document.createElement(`button`);u.className=`omnichat-widget-launcher`,u.setAttribute(`aria-label`,`Open chat`),u.textContent=`💬`;let d=document.createElement(`div`);d.className=`omnichat-widget-panel`;let f=document.createElement(`div`);f.className=`omnichat-widget-header`,f.textContent=`Chat with us`;let p=document.createElement(`div`);p.className=`omnichat-widget-messages`;let m=document.createElement(`div`);m.className=`omnichat-widget-input-row`;let h=document.createElement(`input`);h.className=`omnichat-widget-input`,h.placeholder=`Type a message…`;let g=document.createElement(`button`);g.className=`omnichat-widget-send`,g.textContent=`Send`,m.appendChild(h),m.appendChild(g),d.appendChild(f),d.appendChild(p),d.appendChild(m),document.body.appendChild(u),document.body.appendChild(d);let _=!1;function v(e){if(!s.has(e.id)){if(e.direction===`inbound`){let t=c.findIndex(t=>t.text===e.text);t!==-1&&(c[t].element.remove(),c.splice(t,1))}s.add(e.id),l=e.id,a(p,e)}}async function y(){if(l)try{let{data:t}=await r(e,o,`/messages?since=${encodeURIComponent(l)}`);t.forEach(v)}catch{}}async function b(){if(!_){_=!0;try{let{data:t}=await r(e,o,`/bootstrap`);t.messages.length===0&&t.welcomeMessage&&a(p,{id:`welcome`,direction:`outbound`,text:t.welcomeMessage,timestamp:new Date().toISOString()}),t.messages.forEach(v),setInterval(y,4e3)}catch{_=!1}}}let x=!1;async function S(){if(x)return;let t=h.value.trim();if(!t)return;x=!0,h.value=``,h.disabled=!0,g.disabled=!0;let n=a(p,{id:`pending-${Date.now()}`,direction:`inbound`,text:t,timestamp:new Date().toISOString()});c.push({text:t,element:n});try{let{data:n}=await r(e,o,`/messages`,{method:`POST`,body:JSON.stringify({text:t})});v(n.message)}catch{}finally{x=!1,h.disabled=!1,g.disabled=!1,h.focus()}}g.addEventListener(`click`,S),h.addEventListener(`keydown`,e=>{e.key===`Enter`&&(e.preventDefault(),S())}),u.addEventListener(`click`,()=>{d.classList.toggle(`open`)&&b()})}document.readyState===`loading`?document.addEventListener(`DOMContentLoaded`,o):o()})();