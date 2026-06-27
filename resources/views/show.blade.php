<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ecoute Diagnostics — {{ $capture->ai_response['title'] ?? 'Issue #' . substr($capture->id, 0, 8) }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@100..900&display=swap');

        :root {
            --bg-main: #0b0f19;
            --bg-card: #151b2c;
            --border-color: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            font-family: 'Geist', system-ui, -apple-system, sans-serif;
            line-height: 1.5;
            padding: 24px;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-badge {
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: color 0.15s ease;
        }

        .btn-back:hover {
            color: var(--text-main);
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        @media (max-width: 1024px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .card-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            font-size: 13.5px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-body {
            padding: 20px;
        }

        /* Video / Screenshot Section */
        .video-container {
            position: relative;
            background: #000;
            aspect-ratio: 16 / 10;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        video, .screenshot-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .no-media {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Timeline Section */
        .timeline-scroll {
            height: 400px;
            overflow-y: auto;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .timeline-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid transparent;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .timeline-item:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.05);
        }

        .timeline-item.active {
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.3);
            transform: scale(1.01);
        }

        .timeline-time {
            font-family: 'Geist Mono', monospace;
            font-size: 11px;
            color: var(--text-muted);
            background: rgba(255,255,255,0.05);
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 2px;
        }

        .timeline-icon {
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 20px;
        }

        .timeline-content {
            flex: 1;
            font-size: 12.5px;
            word-break: break-all;
        }

        .timeline-item.type-click .timeline-icon { color: var(--primary); }
        .timeline-item.type-network .timeline-icon { color: var(--success); }
        .timeline-item.type-console .timeline-icon { color: var(--warning); }
        .timeline-item.active .timeline-content { color: var(--text-main); font-weight: 500; }

        .timeline-badge {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 4px;
            border-radius: 3px;
            text-transform: uppercase;
            margin-right: 6px;
        }
        .badge-error { background: rgba(239, 68, 110, 0.15); color: var(--danger); }
        .badge-warn { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
        .badge-log { background: rgba(255, 255, 255, 0.1); color: var(--text-muted); }

        /* Tabs Section */
        .tabs-header {
            display: flex;
            gap: 8px;
            background: rgba(0, 0, 0, 0.2);
            padding: 8px 16px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .tab-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            border: 1px solid transparent;
            border-bottom: none;
            transition: all 0.15s ease;
        }

        .tab-btn:hover {
            color: var(--text-main);
        }

        .tab-btn.active {
            color: var(--primary);
            background: var(--bg-card);
            border-color: var(--border-color);
            margin-bottom: -1px;
        }

        .tab-content {
            display: none;
            padding: 24px;
            max-height: 400px;
            overflow-y: auto;
        }

        .tab-content.active {
            display: block;
        }

        /* Code Inspection and Storage Tables */
        pre {
            background: rgba(0, 0, 0, 0.3);
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Geist Mono', monospace;
            font-size: 12px;
            border: 1px solid rgba(255,255,255,0.03);
        }

        .storage-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .storage-table th, .storage-table td {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .storage-table th {
            color: var(--text-muted);
            font-weight: 600;
            background: rgba(0, 0, 0, 0.1);
        }

        .storage-table td.key {
            font-family: 'Geist Mono', monospace;
            font-weight: 600;
            width: 30%;
            color: var(--primary);
        }

        .storage-table td.val {
            font-family: 'Geist Mono', monospace;
            color: var(--text-muted);
        }
        .storage-table-val-wrapper {
            max-width: 450px;
            max-height: 120px;
            overflow-y: auto;
            word-break: break-all;
            white-space: pre-wrap;
        }

        .empty-table {
            color: var(--text-muted);
            font-style: italic;
            text-align: center;
            padding: 24px 0;
        }

        .metadata-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metadata-item {
            background: rgba(255, 255, 255, 0.02);
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .metadata-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .metadata-val {
            font-size: 13px;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <header>
        <div>
            <a href="javascript:history.back()" class="btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to Captures
            </a>
        </div>
        <h1>
            <span>{{ $capture->ai_response['title'] ?? 'Issue #' . substr($capture->id, 0, 8) }}</span>
            <span class="header-badge">{{ $capture->ai_response['type'] ?? 'Issue' }}</span>
        </h1>
    </header>

    <div class="metadata-grid">
        <div class="metadata-item">
            <div class="metadata-label">URL</div>
            <div class="metadata-val"><a href="{{ $capture->interaction['url'] ?? '#' }}" target="_blank" style="color: var(--primary); text-decoration: none;">{{ $capture->interaction['url'] ?? 'Unknown' }}</a></div>
        </div>
        <div class="metadata-item">
            <div class="metadata-label">Selector</div>
            <div class="metadata-val" style="font-family: 'Geist Mono', monospace; font-size: 12px;">{{ $capture->element_selector }}</div>
        </div>
        <div class="metadata-item">
            <div class="metadata-label">Date Captured</div>
            <div class="metadata-val">{{ $capture->created_at->format('Y-m-d H:i:s') }}</div>
        </div>
        <div class="metadata-item">
            <div class="metadata-label">Input Method</div>
            <div class="metadata-val" style="text-transform: capitalize;">{{ $capture->interaction['input_method'] ?? 'Text' }}</div>
        </div>
    </div>

    <div class="grid-container">
        <!-- Left: Video / Media Player -->
        <div class="card">
            <div class="card-header">
                <span>Screen Capture Recording</span>
                @if($capture->recording_path)
                    <span style="color: var(--success); font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                        <span style="width: 6px; height: 6px; background: var(--success); border-radius: 50%;"></span>
                        WebM Recording
                    </span>
                @endif
            </div>
            <div class="video-container">
                @if($capture->recording_path)
                    @php
                        $disk = $capture->recording_disk ?? config('ecoute.screenshot.disk', 'public');
                        $videoUrl = \Illuminate\Support\Facades\Storage::disk($disk)->url($capture->recording_path);
                    @endphp
                    <video id="diagnostics-video" src="{{ $videoUrl }}" controls playsinline></video>
                @elseif($capture->screenshot_path)
                    @php
                        $disk = $capture->screenshot_disk ?? config('ecoute.screenshot.disk', 'public');
                        $imgUrl = \Illuminate\Support\Facades\Storage::disk($disk)->url($capture->screenshot_path);
                    @endphp
                    <img src="{{ $imgUrl }}" class="screenshot-img" alt="Capture Screenshot" />
                @else
                    <div class="no-media">No visual media captured for this issue.</div>
                @endif
            </div>
        </div>

        <!-- Right: Synchronized Timeline -->
        <div class="card" style="display: flex; flex-direction: column;">
            <div class="card-header">
                <span>Playback Timeline Logs</span>
            </div>
            <div class="timeline-scroll">
                @php
                    $timeline = $capture->interaction['diagnostics']['timeline'] ?? [];
                @endphp
                @forelse($timeline as $event)
                    @php
                        $type = $event['type'] ?? 'unknown';
                        $icon = match($type) {
                            'click' => '🖱️',
                            'input' => '⌨️',
                            'console' => '🖥️',
                            'network' => '🌐',
                            default => '⏱️',
                        };
                    @endphp
                    <div class="timeline-item type-{{ $type }}" data-raw-time="{{ $event['timestamp'] ?? '' }}">
                        <div class="timeline-time">{{ explode('.', $event['timestamp'] ?? '')[0] }}</div>
                        <div class="timeline-icon">{{ $icon }}</div>
                        <div class="timeline-content">
                            @if($type === 'click')
                                <span>User clicked: <code>{{ $event['label'] ?? '' }}</code></span>
                            @elseif($type === 'input')
                                <span>Form input: <code>{{ $event['label'] ?? '' }}</code></span>
                            @elseif($type === 'console')
                                @php
                                    $level = $event['level'] ?? 'log';
                                    $args = implode(' ', (array) ($event['args'] ?? []));
                                @endphp
                                <span class="timeline-badge badge-{{ $level }}">{{ $level }}</span>
                                <span style="font-family: 'Geist Mono', monospace; font-size: 11.5px;">{{ $args }}</span>
                            @elseif($type === 'network')
                                @php
                                    $status = $event['status'] ?? 0;
                                    $statusText = $status === 0 ? 'FAIL' : (string) $status;
                                    $isError = ($status >= 400 || $status === 0);
                                @endphp
                                <span class="timeline-badge {{ $isError ? 'badge-error' : 'badge-log' }}">{{ $statusText }}</span>
                                <span>Fetch <code>{{ $event['method'] ?? 'GET' }}</code> <code>{{ $event['url'] ?? '' }}</code> ({{ $event['duration'] ?? 0 }}ms)</span>
                            @else
                                <span>{{ $event['message'] ?? '' }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-table">No interaction events captured.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Tabs: Code inspection and State snapshots -->
    <div class="card">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="switchTab(event, 'tab-prompt')">User Explanation</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-localstorage')">LocalStorage</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-sessionstorage')">SessionStorage</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-cookies')">Cookies</button>
            <button class="tab-btn" onclick="switchTab(event, 'tab-html')">DOM HTML</button>
        </div>

        <!-- Tab Content: User Prompt & AI Response -->
        <div id="tab-prompt" class="tab-content active">
            <h3 style="margin-bottom: 8px; font-size: 14px;">User Description</h3>
            <p style="background: rgba(0,0,0,0.15); padding: 14px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--border-color);">
                {{ $capture->user_prompt }}
            </p>
            @if($capture->ai_response && !empty($capture->ai_response['suggested_fix']))
                <h3 style="margin-bottom: 8px; font-size: 14px; color: var(--success);">AI Suggested Fix</h3>
                <pre style="white-space: pre-wrap; font-family: inherit; font-size: 13.5px; border-left: 3px solid var(--success);">{{ $capture->ai_response['suggested_fix'] }}</pre>
            @endif
        </div>

        <!-- Tab Content: LocalStorage -->
        <div id="tab-localstorage" class="tab-content">
            @php
                $localStorage = $capture->interaction['diagnostics']['state']['localStorage'] ?? [];
            @endphp
            @if(!empty($localStorage))
                <table class="storage-table">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($localStorage as $key => $val)
                            <tr>
                                <td class="key">{{ $key }}</td>
                                <td class="val"><div class="storage-table-val-wrapper">{{ $val }}</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-table">localStorage is empty or was not captured.</div>
            @endif
        </div>

        <!-- Tab Content: SessionStorage -->
        <div id="tab-sessionstorage" class="tab-content">
            @php
                $sessionStorage = $capture->interaction['diagnostics']['state']['sessionStorage'] ?? [];
            @endphp
            @if(!empty($sessionStorage))
                <table class="storage-table">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sessionStorage as $key => $val)
                            <tr>
                                <td class="key">{{ $key }}</td>
                                <td class="val"><div class="storage-table-val-wrapper">{{ $val }}</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-table">sessionStorage is empty or was not captured.</div>
            @endif
        </div>

        <!-- Tab Content: Cookies -->
        <div id="tab-cookies" class="tab-content">
            @php
                $cookies = $capture->interaction['diagnostics']['state']['cookies'] ?? '';
            @endphp
            @if(!empty($cookies))
                <table class="storage-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(explode(';', $cookies) as $cookie)
                            @php
                                $parts = explode('=', trim($cookie), 2);
                            @endphp
                            @if(count($parts) === 2)
                                <tr>
                                    <td class="key">{{ $parts[0] }}</td>
                                    <td class="val"><div class="storage-table-val-wrapper">{{ $parts[1] }}</div></td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-table">No cookies captured.</div>
            @endif
        </div>

        <!-- Tab Content: DOM HTML -->
        <div id="tab-html" class="tab-content">
            <h3 style="margin-bottom: 8px; font-size: 13px; color: var(--primary);">Selected Element HTML</h3>
            <pre><code class="language-html">{{ $capture->element_html }}</code></pre>
            @if($capture->parent_html)
                <h3 style="margin-top: 20px; margin-bottom: 8px; font-size: 13px; color: var(--text-muted);">Parent Element HTML</h3>
                <pre><code class="language-html">{{ $capture->parent_html }}</code></pre>
            @endif
        </div>
    </div>

    <script>
        function switchTab(e, tabId) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));

            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));

            document.getElementById(tabId).classList.add('active');
            e.currentTarget.classList.add('active');
        }

        // Timeline Video Sync Logic
        (function() {
            const video = document.getElementById('diagnostics-video');
            const timelineItems = document.querySelectorAll('.timeline-item');
            if (timelineItems.length === 0) return;

            const parseTimeToSeconds = (timeStr) => {
                if (!timeStr) return 0;
                const timeOnly = timeStr.includes(' ') ? timeStr.split(' ')[1] : timeStr;
                const parts = timeOnly.split('.');
                const hms = parts[0].split(':').map(Number);
                const ms = parts[1] ? Number(parts[1]) / 1000 : 0;
                return hms[0] * 3600 + hms[1] * 60 + hms[2] + ms;
            };

            // Calculate absolute offsets
            let lastEventTime = 0;
            const items = [];
            timelineItems.forEach(item => {
                const rawTime = item.getAttribute('data-raw-time');
                const absSecs = parseTimeToSeconds(rawTime);
                items.push({ el: item, absSecs: absSecs });
                if (absSecs > lastEventTime) {
                    lastEventTime = absSecs;
                }
            });

            // Map timeline items to relative video playback positions
            if (video) {
                video.addEventListener('loadedmetadata', function() {
                    const duration = video.duration || 15;
                    items.forEach(item => {
                        // The last timeline event is the submit trigger, matching the end of the recording.
                        const offset = lastEventTime - item.absSecs;
                        const relativeVideoTime = duration - offset;
                        item.el.setAttribute('data-video-time', Math.max(0, relativeVideoTime).toFixed(2));
                    });
                });

                // Play head tracking and highlighting
                video.addEventListener('timeupdate', function() {
                    const curTime = video.currentTime;
                    let activeItem = null;

                    items.forEach(item => {
                        const eventTime = parseFloat(item.el.getAttribute('data-video-time') || '0');
                        if (curTime >= eventTime) {
                            activeItem = item.el;
                        }
                        item.el.classList.remove('active');
                    });

                    if (activeItem) {
                        activeItem.classList.add('active');
                        // Auto-scroll timeline to current active log
                        activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });

                // Clicking on a timeline log jumps video directly to that event timestamp
                items.forEach(item => {
                    item.el.addEventListener('click', function() {
                        const targetTime = parseFloat(item.el.getAttribute('data-video-time') || '0');
                        video.currentTime = targetTime;
                        video.play();
                    });
                });
            }
        })();
    </script>
</body>
</html>
