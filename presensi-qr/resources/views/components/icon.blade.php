@props(['name'])
<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@if($name === 'dashboard')<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>
@elseif($name === 'student')<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>
@elseif($name === 'school')<path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/>
@elseif($name === 'scan')<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>
@elseif($name === 'report')<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6"/><path d="M9 16h6"/>
@elseif($name === 'logout')<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>
@elseif($name === 'plus')<path d="M12 5v14M5 12h14"/>
@elseif($name === 'pencil')<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>
@elseif($name === 'trash')<path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
@elseif($name === 'idcard')<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="12" r="2"/><path d="M13 10h5M13 14h5"/>
@elseif($name === 'search')<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
@elseif($name === 'download')<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>
@elseif($name === 'menu')<path d="M4 6h16M4 12h16M4 18h16"/>
@elseif($name === 'x')<path d="M18 6 6 18M6 6l12 12"/>
@elseif($name === 'check')<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>
@elseif($name === 'alert')<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
@elseif($name === 'xcircle')<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>
@elseif($name === 'clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
@elseif($name === 'printer')<path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7"/>
@elseif($name === 'user')<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
@elseif($name === 'key')<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2 21 2M16 7l3 3"/>
@elseif($name === 'refresh')<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>
@elseif($name === 'keyboard')<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/>
@elseif($name === 'back')<path d="M19 12H5M12 19l-7-7 7-7"/>
@elseif($name === 'info')<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>
@elseif($name === 'camera')<path d="M3 8a2 2 0 0 1 2-2h2l1.5-2h7L17 6h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="3.5"/>
@elseif($name === 'volume')<path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/>
@elseif($name === 'volumeoff')<path d="M11 5 6 9H3v6h3l5 4z"/><path d="m22 9-6 6M16 9l6 6"/>
@elseif($name === 'users')<circle cx="9" cy="8" r="3.5"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a7 7 0 0 1 4 6.5"/>
@elseif($name === 'qr')<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v.01M14 21h.01M17 21h4v-4"/>
@elseif($name === 'chevron')<path d="m6 9 6 6 6-6"/>
@elseif($name === 'letter')<path d="M4 4h16v13a2 2 0 0 1-2 2H8l-4 3z"/><path d="M8 9h8M8 13h5"/>
@elseif($name === 'calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>
@elseif($name === 'upload')<path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
@endif
</svg>
