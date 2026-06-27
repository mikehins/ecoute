(function(){const l=document.currentScript??document.querySelector('script[src*="vendor/ecoute/overlay.js"]'),O=l==null?void 0:l.dataset.endpoint,F=l==null?void 0:l.dataset.preview,H=l==null?void 0:l.dataset.templates,E=l==null?void 0:l.dataset.csrf;if(!O||!E){console.error("[Ecoute] Missing data-endpoint or data-csrf on script tag.");return}const _=(l==null?void 0:l.dataset.diagnostics)==="true",$=50,Q=100;let T=[],C=[];if(_){let e=function(t,o,n,i){var a=String(t).replace(/[?#].*$/,"").slice(0,2e3);C.length>=Q&&C.shift(),C.push({url:a,method:o,status:n,duration:Math.round(i),timestamp:new Date().toISOString()})};var ke=e;(function(){var o={log:console.log,warn:console.warn,error:console.error};function n(i,a){T.length>=$&&T.shift(),T.push({level:i,args:Array.from(a).map(function(r){if(r instanceof Error)return r.message;if(typeof r=="object")try{return JSON.stringify(r).slice(0,200)}catch{return String(r).slice(0,200)}return String(r).slice(0,500)}).slice(0,10),timestamp:new Date().toISOString()})}console.log=function(){n("log",arguments),o.log.apply(console,arguments)},console.warn=function(){n("warn",arguments),o.warn.apply(console,arguments)},console.error=function(){n("error",arguments),o.error.apply(console,arguments)}})(),function(){var o=window.fetch;window.fetch=function(i,a){var r=performance.now(),s=a&&a.method||"GET",b=typeof i=="string"?i:i instanceof Request?i.url:String(i);return o.apply(this,arguments).then(function(d){return e(b,s,d.status,performance.now()-r),d}).catch(function(d){throw e(b,s,0,performance.now()-r),d})};var n=window.XMLHttpRequest;window.XMLHttpRequest=function(){var i=new n,a="GET",r="",s=0,b=i.open;i.open=function(ye,xe){return a=ye,r=String(xe),b.apply(i,arguments)};var d=i.send;return i.send=function(){return s=performance.now(),i.addEventListener("loadend",function(){e(r,a,i.status,performance.now()-s)}),d.apply(i,arguments)},i},window.XMLHttpRequest.prototype=n.prototype}(),function(){if(window.PerformanceObserver)try{var o=new PerformanceObserver(function(n){n.getEntries().forEach(function(i){if(!(i.initiatorType==="fetch"||i.initiatorType==="xmlhttprequest")){var a=i.name;a.indexOf(window.location.origin)===0&&e(a,"GET",0,i.duration)}})});o.observe({type:"resource",buffered:!0})}catch{}}()}(function(){if(!window.htmlToImage)return new Promise(function(t,o){const n=document.createElement("script");n.src="https://cdn.jsdelivr.net/npm/html-to-image@1.11.11/dist/html-to-image.js",n.integrity="sha512-zPMZ/3MBK+R1rv6KcBFcf7rGwLnKS+xtB2OnWkAxgC6anqxlDhl/wMWtDbiYI4rgi/NrCJdXrmNGB8pIq+slJQ==",n.crossOrigin="anonymous",n.onload=t,n.onerror=o,document.head.appendChild(n)})})();function ee(e){const t=e.toLowerCase().split("+"),o=function(n){return t.includes(n)};return{ctrl:o("ctrl"),alt:o("alt"),shift:o("shift"),meta:o("meta"),key:t.find(function(n){return!["ctrl","alt","shift","meta"].includes(n)})||""}}function te(e){const t=[];return e.ctrl&&t.push("Ctrl"),e.alt&&t.push("Alt"),e.shift&&t.push("Shift"),e.meta&&t.push("Meta"),t.push(e.key.toUpperCase()),t.join("+")}const v=ee((l==null?void 0:l.dataset.shortcut)||"ctrl+shift+e");let m=null,x=!1;const c=re();document.body.appendChild(c),document.addEventListener("keydown",function(e){const t=document.activeElement&&document.activeElement.tagName;if(t==="INPUT"||t==="TEXTAREA"||t==="SELECT")return;if(e.key==="Escape"&&x){q();return}const o=e.key.toLowerCase(),n=v.key;let i=o===n;!i&&/^\d$/.test(n)&&(i=e.code==="Digit"+n),e.ctrlKey===v.ctrl&&e.altKey===v.alt&&e.shiftKey===v.shift&&e.metaKey===v.meta&&i&&(x?q():oe())});function oe(){x=!0,document.body.classList.add("ecoute-active"),document.addEventListener("click",M,!0),u("Click any element to capture it. Press "+te(v)+" to cancel."),ae()}function q(){x=!1,document.body.classList.remove("ecoute-active"),document.removeEventListener("click",M,!0),B(),ie(),m=null}function M(e){c.contains(e.target)||(e.preventDefault(),e.stopPropagation(),B(),m=e.target,m.classList.add("ecoute-highlight"),document.body.classList.remove("ecoute-active"),document.removeEventListener("click",M,!0),x=!1,document.body.offsetWidth,ne())}function B(){m&&m.classList.remove("ecoute-highlight"),document.querySelectorAll(".ecoute-highlight").forEach(function(e){e.classList.remove("ecoute-highlight")})}function re(){const e=document.createElement("div");return e.id="ecoute-panel",e.innerHTML=`
            <div id="ecoute-panel-inner">
                <div id="ecoute-panel-header">
                    <span id="ecoute-panel-title">Ecoute Feedback</span>
                    <button id="ecoute-close" aria-label="Close Ecoute panel">&times;</button>
                </div>
                <div id="ecoute-form-view">
                    <div id="ecoute-template-wrap" style="display:none">
                        <label for="ecoute-template">Type</label>
                        <select id="ecoute-template"></select>
                    </div>
                    <div id="ecoute-prompt-header">
                        <label for="ecoute-prompt">Describe the issue:</label>
                        <div id="ecoute-prompt-tools">
                            <span id="ecoute-rec-timer"></span>
                            <button id="ecoute-rec-btn" type="button" title="Record screen" aria-label="Record screen">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button id="ecoute-mic-btn" type="button" title="Dictate description" aria-label="Dictate description">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>
                            </button>
                        </div>
                    </div>
                    <textarea id="ecoute-prompt" rows="4" maxlength="2000" placeholder="What's wrong here?"></textarea>
                    <div id="ecoute-actions">
                        <button id="ecoute-preview-btn">Preview</button>
                        <span id="ecoute-status" class="ecoute-status"></span>
                    </div>
                </div>
                <div id="ecoute-preview-view" style="display:none">
                    <input id="ecoute-preview-title-input" type="text" maxlength="500" placeholder="Issue title" />
                    <div id="ecoute-preview-sections"></div>
                    <textarea id="ecoute-preview-fallback" rows="13" spellcheck="false" style="display:none"></textarea>
                    <div id="ecoute-preview-actions">
                        <button id="ecoute-edit-btn">← Edit</button>
                        <button id="ecoute-submit">Send</button>
                        <span id="ecoute-preview-status" class="ecoute-status"></span>
                    </div>
                </div>
            </div>
        `,e.style.cssText=["position:fixed","bottom:20px","right:20px","z-index:2147483647","width:340px","background:rgba(255,255,255,.97)","backdrop-filter:blur(20px)","-webkit-backdrop-filter:blur(20px)","border:1px solid rgba(0,0,0,.06)","border-radius:14px","box-shadow:0 4px 24px rgba(0,0,0,.08),0 0 0 1px rgba(0,0,0,.04)","font-family:Geist,system-ui,-apple-system,sans-serif","font-size:13px","display:none"].join(";"),e.querySelector("#ecoute-close").addEventListener("click",q),e.querySelector("#ecoute-rec-btn").addEventListener("click",pe),e.querySelector("#ecoute-mic-btn").addEventListener("click",he),e.querySelector("#ecoute-preview-btn").addEventListener("click",ue),e.querySelector("#ecoute-edit-btn").addEventListener("click",K),e.querySelector("#ecoute-submit").addEventListener("click",de),e}function ne(e){c.style.display="block",K(),c.querySelector("#ecoute-prompt").value="",D=!1,u(""),c.querySelector("#ecoute-prompt").focus()}function ie(){c.style.display="none"}function u(e){const t=c.querySelector("#ecoute-status");t&&(t.textContent=e);const o=c.querySelector("#ecoute-preview-status");o&&(o.textContent=e)}function X(e,t,o){const n=document.createElement("div");n.id="ecoute-toast",n.innerHTML='<div id="ecoute-toast-title">'+e+'</div><div id="ecoute-toast-message">'+t+"</div>"+(o?'<a id="ecoute-toast-link" href="'+o+'" target="_blank">View issue &rarr;</a>':""),document.body.appendChild(n),requestAnimationFrame(function(){requestAnimationFrame(function(){n.classList.add("ecoute-toast-visible")})}),setTimeout(function(){n.classList.remove("ecoute-toast-visible"),n.addEventListener("transitionend",function(){n.remove()},{once:!0})},6e3)}function ae(){if(!H)return;const e=c.querySelector("#ecoute-template-wrap"),t=c.querySelector("#ecoute-template");t.innerHTML='<option value="">Loading…</option>',t.disabled=!0,e.style.display="block",fetch(H,{headers:{Accept:"application/json","X-CSRF-TOKEN":E}}).then(function(o){return o.json()}).then(function(o){if(!o.length){e.style.display="none";return}t.innerHTML=o.map(function(n){return'<option value="'+n.value+'">'+n.label+"</option>"}).join(""),t.disabled=!1}).catch(function(){e.style.display="none"})}function K(){c.querySelector("#ecoute-form-view").style.display="block",c.querySelector("#ecoute-preview-view").style.display="none",c.querySelector("#ecoute-panel-title").textContent="Ecoute Feedback",c.style.width="340px"}function ce(e,t,o){c.querySelector("#ecoute-form-view").style.display="none",c.querySelector("#ecoute-preview-view").style.display="block",c.querySelector("#ecoute-panel-title").textContent="Issue Preview",c.querySelector("#ecoute-preview-title-input").value=e,c.style.width="560px";const n=c.querySelector("#ecoute-preview-sections"),i=c.querySelector("#ecoute-preview-fallback");o&&o.length?(n.innerHTML=o.map(function(a,r){return se(a,r)}).join(""),n.style.display="block",i.style.display="none"):(n.style.display="none",i.style.display="block",i.value=t)}function se(e,t){const o="ecoute-section-"+t,n=e.description?'<div class="ecoute-section-desc">'+g(e.description)+"</div>":"";let i;if(e.type==="dropdown"&&e.options&&e.options.length){const a=e.options.map(function(r){const s=r===e.value?" selected":"";return'<option value="'+g(r)+'"'+s+">"+g(r)+"</option>"}).join("");i='<select id="'+o+'" class="ecoute-section-select">'+a+"</select>"}else e.type==="input"?i='<input id="'+o+'" type="text" class="ecoute-section-input" value="'+g(e.value||"")+'" />':i='<textarea id="'+o+'" class="ecoute-section-textarea" rows="4">'+g(e.value||"")+"</textarea>";return'<div class="ecoute-section" data-label="'+g(e.label)+'"><label class="ecoute-section-label" for="'+o+'">'+g(e.label)+"</label>"+n+i+"</div>"}function g(e){return String(e).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;")}function le(){const e=c.querySelector("#ecoute-preview-sections"),t=c.querySelector("#ecoute-preview-fallback");return t.style.display!=="none"?t.value.trim():Array.from(e.querySelectorAll(".ecoute-section")).map(function(o){const n=o.dataset.label||"",i=o.querySelector("textarea, select, input"),a=i?i.value.trim():"";return"### "+n+`

`+a}).join(`

`)}async function ue(){if(!F){u("Preview not available.");return}k(),S();const e=c.querySelector("#ecoute-prompt").value.trim();if(!e&&!h){u("Please describe the issue.");return}if(!m){u("No element selected. Click an element first.");return}const t=c.querySelector("#ecoute-preview-btn");t.disabled=!0,U(t),u("");const o=await Y(),n=V(m,e,o);try{const i=await fetch(F,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":E,Accept:"application/json"},body:JSON.stringify(n)});if(!i.ok){const r=await i.json().catch(function(){return{}}),s=r.errors?Object.keys(r.errors)[0]:null,b=s?r.errors[s][0]:null,d=s?s+": "+b:r.message||i.status;u("Error: "+d);return}const a=await i.json();ce(a.title,a.body,a.sections||[])}catch{u("Network error. Please try again.")}finally{t.disabled=!1,W(t,"Preview")}}async function de(){k(),S(),await I;var e=c.querySelector("#ecoute-prompt").value.trim();if(!e&&!h){u("Please describe the issue.");return}if(!e&&h&&(e="[Issue described verbally — see recording]"),!m){u("No element selected. Click an element first.");return}const t=c.querySelector("#ecoute-submit");t.disabled=!0,U(t,me),u("Capturing…");const o=await Y(),n=V(m,e,o);if(c.querySelector("#ecoute-preview-view").style.display!=="none"){const a=c.querySelector("#ecoute-preview-title-input").value.trim();a&&(n.title_override=a);const r=le();r&&(n.body_override=r)}try{const a=await fetch(O,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":E,Accept:"application/json"},body:JSON.stringify(n)});if(a.status===429){u("Too many submissions. Please wait and try again.");return}if(!a.ok){const s=await a.json().catch(function(){return{}});u("Error: "+(s.message||a.status));return}const r=await a.json();r.deduplicated?X("Already captured","Similar feedback was already received.",null):X("Issue captured","Your feedback is being processed by AI.",r.issue_url??null),q()}catch(a){console.error("[Ecoute] Submission error:",a),u("Network error. Please try again.")}finally{t.disabled=!1,W(t,"Send")}}let p=null,N=[],w=null,h=null,I=Promise.resolve(),L=null,R=0,z=null;function pe(){p&&p.state==="recording"?k():fe()}async function fe(){var n,i;var e=(((i=(n=window.ecouteConfig)==null?void 0:n.recording)==null?void 0:i.maxDuration)||15)*1e3;try{w=await navigator.mediaDevices.getDisplayMedia({video:!0,audio:!0})}catch(a){a.name!=="AbortError"&&u("Screen recording not available or was cancelled.");return}N=[],h=null,I=new Promise(function(a){L=a}),R=0;var t=MediaRecorder.isTypeSupported("video/webm;codecs=vp9")?"video/webm;codecs=vp9":"video/webm";p=new MediaRecorder(w,{mimeType:t}),p.ondataavailable=function(a){a.data.size>0&&N.push(a.data)},p.onstop=async function(){var a=new Blob(N,{type:t});h=await new Promise(function(r){var s=new FileReader;s.onloadend=function(){r(s.result)},s.readAsDataURL(a)}),w&&(w.getTracks().forEach(function(r){r.stop()}),w=null),L&&(L(),L=null)},w.getVideoTracks()[0].addEventListener("ended",function(){k()}),p.start(1e3);var o=c.querySelector("#ecoute-rec-btn");o.classList.add("ecoute-recording"),o.title="Stop recording",G(),z=setInterval(G,1e3),e>0&&setTimeout(function(){p&&p.state==="recording"&&k()},e)}function k(){z&&(clearInterval(z),z=null);var e=c.querySelector("#ecoute-rec-btn");e.classList.remove("ecoute-recording"),e.title="Record screen";var t=c.querySelector("#ecoute-rec-timer");return t&&(t.style.display="none",t.textContent=""),p&&p.state==="recording"?(p.stop(),I):Promise.resolve()}function G(){R++;var e=c.querySelector("#ecoute-rec-timer");if(e){e.style.display="inline";var t=Math.floor(R/60),o=R%60;e.textContent=t+":"+(o<10?"0":"")+o}}var be=["Thinking   ","Analyzing   ","Bip bop   ","Inspecting   ","AI at work   ","Almost there   "],me=["Capturing   ","Sending   ","Processing   "],P=null,A=0;function U(e,t){var o=t||be;A=0,e.textContent=o[0],e.classList.add("ecoute-loading"),P=setInterval(function(){A=(A+1)%o.length,e.textContent=o[A]},1800)}function W(e,t){P&&(clearInterval(P),P=null),e.classList.remove("ecoute-loading"),e.textContent=t}let f=null,j=!1,y="",D=!1;function ge(){if(f)return f;var e=window.SpeechRecognition||window.webkitSpeechRecognition;return e?(f=new e,f.continuous=!0,f.interimResults=!0,f.lang=document.documentElement.lang||"en-US",f):null}function he(){j?S():ve()}function ve(){var e=ge(),t=c.querySelector("#ecoute-mic-btn"),o=c.querySelector("#ecoute-prompt");if(!e){u("Voice dictation is not supported in this browser.");return}j=!0,y="",D=!0,t.classList.add("ecoute-recording"),t.title="Stop dictation",e.onresult=function(n){for(var i="",a="",r=n.resultIndex;r<n.results.length;r++){var s=n.results[r];s.isFinal?i+=s[0].transcript:a+=s[0].transcript}i?(o.value=o.value+(o.value?" ":"")+i,y=""):a&&(y=a)},e.onerror=function(n){n.error==="not-allowed"?u("Microphone access denied. Check your browser permissions."):n.error==="no-speech"||n.error!=="aborted"&&console.error("[Ecoute] Speech recognition error:",n.error),S()},e.onend=function(){S()},e.start()}function S(){j=!1;var e=c.querySelector("#ecoute-mic-btn");if(e.classList.remove("ecoute-recording"),e.title="Dictate description",y){var t=c.querySelector("#ecoute-prompt");t.value=t.value+(t.value?" ":"")+y,y=""}f&&(f.onresult=null,f.onerror=null,f.onend=null)}function V(e,t,o){var r;const n={};for(const s of e.attributes)s.name.startsWith("data-")&&(n[s.name]=String(s.value??"").slice(0,500));const i={element_selector:J(e).slice(0,5e3),parent_selector:e.parentElement?J(e.parentElement).slice(0,5e3):null,element_html:e.outerHTML.slice(0,49e3),parent_html:e.parentElement?e.parentElement.outerHTML.slice(0,49e3):null,attributes:n,nearby_text:we(e).map(String),user_prompt:t,interaction:{page_title:document.title,url:window.location.href,timestamp:new Date().toISOString().slice(0,19).replace("T"," "),input_method:D?"voice":"text"}};o&&(i.screenshot=o),_&&(i.diagnostics={console:T.slice(),network:C.slice()}),h&&(i.recording=h);const a=(r=c.querySelector("#ecoute-template"))==null?void 0:r.value;return a&&(i.template=a),i}function J(e){if(!e||e===document.body)return"body";if(e.id)return"#"+CSS.escape(e.id);const t=[];let o=e;for(;o&&o!==document.body;){let n=o.tagName.toLowerCase();if(o.id){n="#"+CSS.escape(o.id),t.unshift(n);break}if(o.className){const a=Array.from(o.classList).filter(function(r){return!r.startsWith("ecoute-")}).slice(0,2).map(CSS.escape).join(".");a&&(n+="."+a)}const i=o.parentElement?Array.from(o.parentElement.children).filter(function(a){return a.tagName===o.tagName}):[];if(i.length>1){const a=i.indexOf(o)+1;n+=":nth-of-type("+a+")"}t.unshift(n),o=o.parentElement}return t.join(" > ")||e.tagName.toLowerCase()}function we(e){const t=[],o=function(n){var a;if(!n)return;const i=(a=n.textContent)==null?void 0:a.trim().slice(0,200);i&&t.push(i)};return o(e),o(e.previousElementSibling),o(e.nextElementSibling),o(e.parentElement),[...new Set(t)].slice(0,10)}async function Y(){var a;if(await new Promise(function(r){setTimeout(r,50)}),window.htmlToImage||await new Promise(function(r){let s=0;const b=setInterval(function(){s+=100,(window.htmlToImage||s>=5e3)&&(clearInterval(b),r())},100)}),!window.htmlToImage)return null;const e=((a=window.ecouteConfig)==null?void 0:a.screenshot)||{},t=e.maxWidth||800,o=e.quality||.5,n=document.querySelectorAll('input[type="password"], .ecoute-sensitive'),i=Array.from(n).map(function(r){return{el:r,originalValue:r.tagName==="INPUT"||r.tagName==="TEXTAREA"?r.value:null,originalHTML:r.tagName==="INPUT"||r.tagName==="TEXTAREA"?null:r.innerHTML,originalStyles:{backgroundColor:r.style.backgroundColor,color:r.style.color,filter:r.style.filter}}});i.forEach(function({el:r}){r.tagName==="INPUT"||r.tagName==="TEXTAREA"?r.value="••••••••":r.textContent="[REDACTED]",r.style.backgroundColor="#f0f0f0",r.style.color="#666666",r.style.filter=r.style.filter?r.style.filter+" blur(4px)":"blur(4px)"});try{const r=window.innerWidth>t?t/window.innerWidth:1;return await window.htmlToImage.toJpeg(document.body,{quality:o,pixelRatio:r,skipFonts:!0,filter:function(s){return s.id!=="ecoute-panel"}})}catch(r){return console.error("[Ecoute] Screenshot capture failed:",r),null}finally{i.forEach(function({el:r,originalValue:s,originalHTML:b,originalStyles:d}){r.tagName==="INPUT"||r.tagName==="TEXTAREA"?r.value=s:r.innerHTML=b,r.style.backgroundColor=d.backgroundColor,r.style.color=d.color,r.style.filter=d.filter})}}(function(){var t=document.createElement("link");t.href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@100..900&display=swap",t.rel="stylesheet",document.head.appendChild(t)})();const Z=document.createElement("style");Z.textContent=`

        .ecoute-active, .ecoute-active * { cursor: crosshair !important; }
        #ecoute-panel, #ecoute-panel * { cursor: auto !important; }
        #ecoute-panel button, #ecoute-panel a, #ecoute-panel [role="button"] { cursor: pointer !important; }
        #ecoute-panel textarea, #ecoute-panel input, #ecoute-panel select { cursor: text !important; }
        #ecoute-panel select { cursor: default !important; }

        .ecoute-highlight {
            outline: 2.5px solid #818cf8 !important;
            outline-offset: 1px !important;
            background-color: rgba(129,140,248,.06) !important;
            border-radius: 4px !important;
        }

        #ecoute-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid #e8eaed;
        }
        #ecoute-panel-title { color: #1e293b; font-size: 13px; font-weight: 600; letter-spacing: -.01em; }
        #ecoute-close {
            background: none; border: none; font-size: 18px; cursor: pointer;
            color: #94a3b8; line-height: 1; padding: 2px 6px; border-radius: 6px;
            transition: color .15s, background .15s;
        }
        #ecoute-close:hover { color: #475569; background: #f1f5f9; }

        #ecoute-form-view, #ecoute-preview-view { padding: 14px; }
        #ecoute-prompt-header {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;
        }
        #ecoute-prompt-header label { color: #334155; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        #ecoute-prompt-tools { display: flex; align-items: center; gap: 6px; }
        #ecoute-rec-timer {
            font-size: 11px; color: #ef4444; font-variant-numeric: tabular-nums;
            font-weight: 600; min-width: 32px; display: none;
        }
        #ecoute-mic-btn, #ecoute-rec-btn {
            display: flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border: 1px solid #e2e8f0; border-radius: 8px;
            background: #fff; color: #64748b; cursor: pointer; padding: 0;
            transition: all .15s ease;
        }
        #ecoute-mic-btn:hover, #ecoute-rec-btn:hover {
            background: #f8fafc; color: #334155; border-color: #cbd5e1;
        }
        #ecoute-mic-btn.ecoute-recording, #ecoute-rec-btn.ecoute-recording {
            background: #fef2f2; border-color: #fecaca; color: #ef4444;
            animation: ecoute-pulse 1.6s ease-in-out infinite;
        }
        @keyframes ecoute-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.15); }
            50%      { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
        }

        #ecoute-template-wrap { margin-bottom: 10px; }
        #ecoute-template-wrap label { display: block; margin-bottom: 4px; color: #334155; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        #ecoute-template {
            width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 8px 10px; font-family: inherit; font-size: 13px; background: #fff; color: #1e293b;
            transition: border-color .15s, box-shadow .15s;
        }
        #ecoute-template:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.12); }

        #ecoute-prompt {
            width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 10px 12px; resize: vertical; font-family: inherit; font-size: 13px;
            line-height: 1.55; color: #1e293b; background: #fafbfc;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        #ecoute-prompt:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.12); background: #fff; }

        #ecoute-actions { display: flex; align-items: center; gap: 10px; margin-top: 12px; }
        #ecoute-preview-btn, #ecoute-edit-btn {
            background: #fff; color: #475569; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 7px 14px; cursor: pointer; font-size: 12.5px; font-weight: 500;
            transition: all .15s ease;
        }
        #ecoute-preview-btn:hover, #ecoute-edit-btn:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
        #ecoute-preview-btn.ecoute-loading, #ecoute-submit.ecoute-loading {
            background: linear-gradient(110deg, #e2e8f0 30%, #f1f5f9 50%, #e2e8f0 70%);
            background-size: 200% 100%;
            animation: ecoute-shimmer 1.8s ease-in-out infinite;
            color: #64748b;
            border-color: #e2e8f0;
            box-shadow: none;
            transform: none;
        }
        @keyframes ecoute-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        @media (prefers-color-scheme: dark) {
            #ecoute-preview-btn.ecoute-loading, #ecoute-submit.ecoute-loading {
                background: linear-gradient(110deg, #1e293b 30%, #334155 50%, #1e293b 70%);
                background-size: 200% 100%;
                color: #64748b;
            }
        }
        #ecoute-submit {
            background: #4f46e5; color: #fff; border: none; border-radius: 8px;
            padding: 7px 16px; cursor: pointer; font-size: 13px; font-weight: 600;
            letter-spacing: -.01em; transition: all .15s ease; box-shadow: 0 1px 2px rgba(79,70,229,.2);
        }
        #ecoute-submit:hover:not(:disabled) { background: #4338ca; box-shadow: 0 2px 8px rgba(79,70,229,.25); transform: translateY(-1px); }
        #ecoute-submit:active:not(:disabled) { transform: scale(.97) translateY(0); }
        #ecoute-submit:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
        .ecoute-status { color: #94a3b8; font-size: 11.5px; }

        #ecoute-preview-title-input {
            width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 9px 12px; font-family: inherit; font-size: 14px; font-weight: 600;
            color: #1e293b; margin-bottom: 14px; background: #fafbfc;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        #ecoute-preview-title-input:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.12); background: #fff; }

        #ecoute-preview-sections { max-height: 380px; overflow-y: auto; }
        .ecoute-section { margin-bottom: 14px; }
        .ecoute-section-label {
            display: block; font-weight: 600; font-size: 12px; color: #334155;
            margin-bottom: 5px; text-transform: uppercase; letter-spacing: .04em;
        }
        .ecoute-section-desc { font-size: 11px; color: #94a3b8; margin-bottom: 6px; line-height: 1.45; }
        .ecoute-section-textarea, .ecoute-section-select, .ecoute-section-input {
            width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 8px 10px; font-family: inherit; font-size: 13px; color: #334155;
            background: #fafbfc; transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .ecoute-section-textarea { resize: vertical; line-height: 1.55; }
        .ecoute-section-textarea:focus, .ecoute-section-select:focus, .ecoute-section-input:focus {
            outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.12); background: #fff;
        }

        #ecoute-preview-fallback {
            width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 10px 12px; font-family: Geist Mono,ui-monospace,SFMono-Regular,Menlo,monospace;
            font-size: 12px; line-height: 1.6; color: #334155; background: #f8fafc; resize: vertical;
            transition: border-color .15s, box-shadow .15s;
        }
        #ecoute-preview-fallback:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.12); background: #fff; }
        #ecoute-preview-actions { display: flex; align-items: center; gap: 8px; margin-top: 14px; padding-top: 12px; border-top: 1px solid #f1f5f9; }

        #ecoute-toast {
            position: fixed; top: 20px; right: 20px; z-index: 2147483647;
            width: 300px; background: rgba(15,23,42,.92); backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            color: #f8fafc; border-radius: 12px; padding: 14px 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.12), 0 1px 4px rgba(0,0,0,.08);
            font-family: Geist,system-ui,-apple-system,sans-serif; font-size: 13px;
            opacity: 0; transform: translateY(-12px) scale(.96);
            transition: opacity .25s cubic-bezier(.16,1,.3,1), transform .25s cubic-bezier(.16,1,.3,1);
            pointer-events: none; border: 1px solid rgba(255,255,255,.08);
        }
        #ecoute-toast.ecoute-toast-visible {
            opacity: 1; transform: translateY(0) scale(1); pointer-events: auto;
        }
        #ecoute-toast-title { font-weight: 600; font-size: 13px; margin-bottom: 3px; letter-spacing: -.01em; }
        #ecoute-toast-message { color: #94a3b8; font-size: 12px; margin-bottom: 8px; line-height: 1.45; }
        #ecoute-toast-link {
            display: inline-block; color: #a5b4fc; text-decoration: none; font-weight: 500; font-size: 12px;
            transition: color .15s;
        }
        #ecoute-toast-link:hover { color: #c7d2fe; }

        @media (prefers-color-scheme: dark) {
            #ecoute-panel { background: rgba(15,23,42,.95) !important; border-color: rgba(255,255,255,.06) !important; box-shadow: 0 4px 24px rgba(0,0,0,.25),0 0 0 1px rgba(255,255,255,.04) !important; }
            .ecoute-highlight {
                outline-color: #a5b4fc !important;
                background-color: rgba(165,180,252,.1) !important;
            }
            #ecoute-panel-header { border-bottom-color: #1e293b; }
            #ecoute-panel-title { color: #e2e8f0; }
            #ecoute-close { color: #64748b; }
            #ecoute-close:hover { color: #cbd5e1; background: #1e293b; }
            #ecoute-prompt-header label { color: #94a3b8; }
            #ecoute-mic-btn, #ecoute-rec-btn {
                background: #1e293b; color: #94a3b8; border-color: #334155;
            }
            #ecoute-mic-btn:hover, #ecoute-rec-btn:hover {
                background: #334155; color: #cbd5e1; border-color: #475569;
            }
            #ecoute-mic-btn.ecoute-recording, #ecoute-rec-btn.ecoute-recording {
                background: #450a0a; border-color: #7f1d1d; color: #fca5a5;
            }
            @keyframes ecoute-pulse {
                0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.25); }
                50%      { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
            }
            #ecoute-template-wrap label { color: #94a3b8; }
            #ecoute-template {
                background: #1e293b; border-color: #334155; color: #e2e8f0;
            }
            #ecoute-template:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.2); }
            #ecoute-prompt {
                background: #1e293b; border-color: #334155; color: #e2e8f0;
            }
            #ecoute-prompt:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.2); background: #0f172a; }
            #ecoute-preview-btn, #ecoute-edit-btn {
                background: #1e293b; color: #94a3b8; border-color: #334155;
            }
            #ecoute-preview-btn:hover, #ecoute-edit-btn:hover {
                background: #334155; color: #cbd5e1; border-color: #475569;
            }
            #ecoute-submit { background: #6366f1; box-shadow: 0 1px 2px rgba(99,102,241,.3); }
            #ecoute-submit:hover:not(:disabled) { background: #818cf8; }
            .ecoute-status { color: #64748b; }
            #ecoute-preview-title-input {
                background: #1e293b; border-color: #334155; color: #e2e8f0;
            }
            #ecoute-preview-title-input:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.2); background: #0f172a; }
            .ecoute-section-label { color: #94a3b8; }
            .ecoute-section-desc { color: #64748b; }
            .ecoute-section-textarea, .ecoute-section-select, .ecoute-section-input {
                background: #1e293b; border-color: #334155; color: #e2e8f0;
            }
            .ecoute-section-textarea:focus, .ecoute-section-select:focus, .ecoute-section-input:focus {
                border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.2); background: #0f172a;
            }
            #ecoute-preview-fallback {
                background: #1e293b; border-color: #334155; color: #e2e8f0;
            }
            #ecoute-preview-fallback:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,.2); background: #0f172a; }
            #ecoute-preview-actions { border-top-color: #1e293b; }
        }
    `,document.head.appendChild(Z)})();
