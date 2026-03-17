<?php
require_once __DIR__ . '/functions.php';

$config = loadConfig();
$playlist = normalizePlaylist(loadPlaylist(), $config);

$slides = array_values(array_filter($playlist, function ($item) {
    return !empty($item['enabled']);
}));

$player = $config['player'] ?? [];
$clock = $config['clock'] ?? [];

if (empty($slides) && !empty($clock['enabled'])) {
    $slides[] = [
        'id' => 'clock-main',
        'type' => 'clock',
        'title' => 'Uhr',
        'enabled' => true,
        'duration' => (int)($clock['duration'] ?? 10),
        'sort' => 10
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Infoscreen2</title>
<style>
html,body{
  margin:0;
  width:100%;
  height:100%;
  overflow:hidden;
  background:<?= h($player['background'] ?? '#ffffff') ?>;
  font-family:Arial,Helvetica,sans-serif;
}
#stage{
  position:relative;
  width:100vw;
  height:100vh;
  background:<?= h($player['background'] ?? '#ffffff') ?>;
}
.slide{
  position:absolute;
  inset:0;
  display:none;
  opacity:0;
  transition:opacity <?= (float)($player['imageFade'] ?? 1.2) ?>s ease;
  background:<?= h($player['background'] ?? '#ffffff') ?>;
}
.slide.active{
  display:block;
  opacity:1;
  z-index:2;
}
.center{
  width:100%;
  height:100%;
  display:flex;
  align-items:center;
  justify-content:center;
}
.media{
  width:100%;
  height:100%;
  object-fit:<?= h($player['fit'] ?? 'contain') ?>;
  background:<?= h($player['background'] ?? '#ffffff') ?>;
}
.clockWrap{
  width:100%;
  height:100%;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  text-align:center;
  background:<?= h($clock['background'] ?? '#ffffff') ?>;
  color:<?= h($clock['textColor'] ?? '#111111') ?>;
}
.clockLogo{
  margin-bottom:30px;
}
.clockLogo img{
  max-height:<?= (int)($clock['logoHeight'] ?? 100) ?>px;
  max-width:40vw;
}
.clockTime{
  font-size:clamp(70px,12vw,180px);
  font-weight:700;
  line-height:1;
}
.clockDate{
  margin-top:22px;
  font-size:clamp(26px,3vw,54px);
}
.websiteFrame{
  width:100%;
  height:100%;
  border:0;
  background:#fff;
}
.emptyState{
  width:100%;
  height:100%;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:42px;
  color:#666;
}
</style>
</head>
<body>
<div id="stage">
<?php if (empty($slides)): ?>
  <div class="emptyState">Keine aktiven Folien vorhanden</div>
<?php else: ?>
  <?php foreach ($slides as $i => $slide): ?>
    <?php $type = $slide['type'] ?? 'image'; ?>
    <section
      class="slide"
      id="slide-<?= $i ?>"
      data-index="<?= $i ?>"
      data-type="<?= h($type) ?>"
      data-duration="<?= (int)($slide['duration'] ?? ($player['defaultDuration'] ?? 8)) ?>"
      data-videomode="<?= h($slide['videoMode'] ?? ($player['videoMode'] ?? 'until_end')) ?>"
    >
    <?php if ($type === 'image'): ?>
      <div class="center">
        <img
          class="media"
          src="<?= h('uploads/' . basename($slide['file'] ?? '')) ?>"
          alt="<?= h($slide['title'] ?? 'Bild') ?>"
          loading="eager"
        >
      </div>
    <?php elseif ($type === 'video'): ?>
      <div class="center">
        <video
          class="media"
          preload="auto"
          <?= !empty($slide['muted']) ? 'muted' : '' ?>
          <?= !empty($player['startMuted']) ? 'muted' : '' ?>
          playsinline
        >
          <source src="<?= h('uploads/' . basename($slide['file'] ?? '')) ?>">
        </video>
      </div>
    <?php elseif ($type === 'website'): ?>
      <iframe
        class="websiteFrame"
        src="<?= h($slide['url'] ?? '') ?>"
        data-refresh="<?= (int)($slide['refreshSeconds'] ?? 0) ?>"
        referrerpolicy="no-referrer"
      ></iframe>
    <?php elseif ($type === 'clock'): ?>
      <div class="clockWrap">
        <?php if (!empty($clock['logo'])): ?>
          <div class="clockLogo">
            <img src="<?= h('uploads/' . basename($clock['logo'])) ?>" alt="Logo">
          </div>
        <?php endif; ?>
        <div class="clockTime" id="clockTime-<?= $i ?>">--:--</div>
        <div class="clockDate" id="clockDate-<?= $i ?>">--</div>
      </div>
    <?php else: ?>
      <div class="emptyState">Unbekannter Folientyp</div>
    <?php endif; ?>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<script>
const slides = Array.from(document.querySelectorAll('.slide'));
let currentIndex = -1;
let timer = null;
let locked = false;
function updateClocks(){
  const now = new Date();
  const showSeconds = <?= !empty($clock['showSeconds']) ? 'true' : 'false' ?>;
  const timeText = now.toLocaleTimeString('de-AT', {
    hour:'2-digit',
    minute:'2-digit',
    second: showSeconds ? '2-digit' : undefined
  });
  const dateText = now.toLocaleDateString('de-AT', {
    weekday:'long',
    day:'2-digit',
    month:'2-digit',
    year:'numeric'
  });

  slides.forEach((slide, i) => {
    if (slide.dataset.type !== 'clock') return;
    const t = document.getElementById('clockTime-' + i);
    const d = document.getElementById('clockDate-' + i);
    if (t) t.textContent = timeText;
    if (d) d.textContent = dateText;
  });
}
function stopMedia(slide){
  if (!slide) return;
  const video = slide.querySelector('video');
  if (video) {
    video.pause();
    try { video.currentTime = 0; } catch(e) {}
  }
}

function scheduleNext(seconds){
  clearTimeout(timer);
  timer = setTimeout(() => {
    showSlide(currentIndex + 1);
  }, Math.max(1, seconds) * 1000);
}

function activateSlide(slide){
  slides.forEach(s => {
    if (s !== slide) {
      s.classList.remove('active');
      stopMedia(s);
    }
  });
  slide.classList.add('active');
}

function showSlide(index){
  if (!slides.length || locked) return;
  locked = true;
  if (index >= slides.length) index = 0;
  if (index < 0) index = 0;
  currentIndex = index;
  const slide = slides[index];
  activateSlide(slide);

  const type = slide.dataset.type || 'image';
  const duration = parseInt(slide.dataset.duration || '8', 10);
  const videoMode = slide.dataset.videomode || 'until_end';

  if (type === 'video') {
    const video = slide.querySelector('video');
    if (!video) {
      scheduleNext(duration);
      locked = false;
      return;
    }

    video.onended = null;
    video.pause();
    try { video.currentTime = 0; } catch(e) {}

    const playPromise = video.play();
    if (playPromise && typeof playPromise.then === 'function') {
      playPromise.catch(() => {});
    }

    if (videoMode === 'until_end') {
      video.onended = () => showSlide(currentIndex + 1);
    } else {
      scheduleNext(duration);
    }
  } else {
    scheduleNext(duration);
  }

  if (type === 'website') {
    const frame = slide.querySelector('iframe');
    const refreshSeconds = parseInt(frame?.dataset?.refresh || '0', 10);
    if (frame && refreshSeconds > 0) {
      setTimeout(() => {
        try { frame.src = frame.src; } catch(e) {}
      }, refreshSeconds * 1000);
    }
  }

  setTimeout(() => { locked = false; }, 200);
}

updateClocks();
setInterval(updateClocks, 1000);

if (slides.length) {
  showSlide(0);
}
</script>
</body>
</html>
