// assets/js/podcast-player.js
(function () {
  // This function runs for each player instance on the page
  const initializePlayer = (playerWrapper) => {
    const episodes = window.casPlayerData.episodes || [];

    /* Element refs */
    const audioEl = playerWrapper.querySelector("#audioEl");
    const playBtn = playerWrapper.querySelector("#btnPlayPause");
    const iconPlay = playerWrapper.querySelector("#iconPlay");
    const iconPause = playerWrapper.querySelector("#iconPause");
    const currentTimeLabel = playerWrapper.querySelector("#currentTimeLabel");
    const totalTimeLabel = playerWrapper.querySelector("#totalTimeLabel");
    const waveformContainer = playerWrapper.querySelector("#waveformContainer");
    const waveformEl = playerWrapper.querySelector("#waveform");
    const progressOverlay = playerWrapper.querySelector("#progressOverlay");
    const scrubHandle = playerWrapper.querySelector("#scrubHandle");
    const volumeTrack = playerWrapper.querySelector("#volumeTrack");
    const volumeFill = playerWrapper.querySelector("#volumeFill");
    const volumeHandle = playerWrapper.querySelector("#volumeHandle");
    const muteBtn = playerWrapper.querySelector("#btnMute");
    const likeBtn = playerWrapper.querySelector("#btnLike");
    const heartOutline = playerWrapper.querySelector("#iconHeartOutline");
    const heartFilled = playerWrapper.querySelector("#iconHeartFilled");
    const playlistToggleBtn = playerWrapper.querySelector("#playlistToggleBtn");
    const playlistContainer = playerWrapper.querySelector("#playlistContainer");
    const playlistList = playerWrapper.querySelector("#playlistList");
    const episodeTitle = playerWrapper.querySelector("#episodeTitle");
    const episodeAuthor = playerWrapper.querySelector("#episodeAuthor");
    const artworkPulse = playerWrapper.querySelector("#artworkPulse");
    const btnPrev = playerWrapper.querySelector("#btnPrev");
    const btnNext = playerWrapper.querySelector("#btnNext");
    const btnShuffle = playerWrapper.querySelector("#btnShuffle");
    const btnRepeat = playerWrapper.querySelector("#btnRepeat");
    const themeToggleBtn = playerWrapper.querySelector("#themeToggleBtn");
    const themeToggleText = playerWrapper.querySelector("#themeToggleText");
    const themeToggleIcon = playerWrapper.querySelector("#themeToggleIcon");

    if (!audioEl) return; // Stop if the core element isn't found

    let currentIndex = 0,
      isPlaying = false,
      isMuted = false,
      isLiked = false,
      draggingSeek = false,
      draggingVolume = false,
      volume = 0.7,
      lastVolume = 0.7,
      speed = 1,
      playlistOpen = false;
    let shuffleOn = false;
    let repeatMode = "off"; // 'off' | 'all' | 'one'

    function iconSVG(name) {
      switch (name) {
        case "mic":
          return `<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.8"><path d="M12 15a4 4 0 0 0 4-4V7a4 4 0 1 0-8 0v4a4 4 0 0 0 4 4Z"/><path d="M19 11a7 7 0 0 1-14 0M12 18v3" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
        case "briefcase":
          return `<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.8"><path d="M3 10h18v7a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-7Z"/><path d="M3 10a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
        case "rocket":
          return `<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.8"><path d="M5 15c-1-4 1-9 5-12 4 3 6 8 5 12M10 6v3m0 4h0M9 19c1.6 1 4.4 1 6 0M8 15h8" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
        case "headphones":
        default:
          return `<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="1.8"><path d="M4 13v5a3 3 0 0 0 3 3h1v-8H7a3 3 0 0 0-3 3Zm13 0h-1v8h1a3 3 0 0 0 3-3v-5a3 3 0 0 0-3-3ZM6 13V11A6 6 0 0 1 12 5v0a6 6 0 0 1 6 6v2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
      }
    }

    const fmt = (t) =>
      isNaN(t)
        ? "0:00"
        : Math.floor(t / 60) +
          ":" +
          (Math.floor(t % 60) < 10 ? "0" : "") +
          Math.floor(t % 60);

    function buildPlaylist() {
      playlistList.innerHTML = "";
      episodes.forEach((ep) => {
        const li = document.createElement("li");
        li.className = "playlist-item";
        li.dataset.index = ep.id;
        li.innerHTML = `<div class="playlist-artwork">${iconSVG(ep.icon)}</div><div class="playlist-meta"><div class="playlist-title">${ep.title}</div><div class="playlist-author">${ep.author}</div></div><div class="playlist-duration">${fmt(ep.duration)}</div>`;
        li.addEventListener("click", () => loadEpisode(ep.id, true));
        playlistList.appendChild(li);
      });
    }

    function highlightPlaylist() {
      playlistList.querySelectorAll(".playlist-item").forEach((li) => {
        li.classList.toggle(
          "active",
          Number(li.dataset.index) === currentIndex
        );
      });
    }

    function loadEpisode(index, autoplay = false) {
      currentIndex = index;
      const ep = episodes[index];
      audioEl.src = ep.src;
      audioEl.playbackRate = speed;
      episodeTitle.textContent = ep.title;
      episodeAuthor.textContent = ep.author;
      totalTimeLabel.textContent = fmt(ep.duration);
      waveformContainer.setAttribute("aria-valuemax", ep.duration);
      highlightPlaylist();
      resetWave();
      if (autoplay) playAudio();
      else pauseAudio();
    }

    function playAudio() {
      audioEl
        .play()
        .then(() => {
          isPlaying = true;
          iconPlay.style.display = "none";
          iconPause.style.display = "block";
          playBtn.setAttribute("aria-label", "Pause");
          waveformEl.classList.add("playing");
          artworkPulse.style.animationPlayState = "running";
          requestAnimationFrame(loop);
        })
        .catch((e) => console.warn(e));
    }

    function pauseAudio() {
      audioEl.pause();
      isPlaying = false;
      iconPlay.style.display = "block";
      iconPause.style.display = "none";
      playBtn.setAttribute("aria-label", "Play");
      waveformEl.classList.remove("playing");
      artworkPulse.style.animationPlayState = "paused";
    }

    function toggleLike() {
      isLiked = !isLiked;
      likeBtn.classList.toggle("liked", isLiked);
      heartOutline.style.display = isLiked ? "none" : "block";
      heartFilled.style.display = isLiked ? "block" : "none";
      likeBtn.setAttribute("aria-pressed", String(isLiked));
    }

    function updateProgress() {
      if (!audioEl) return;
      const current = audioEl.currentTime;
      const total = audioEl.duration || episodes[currentIndex].duration;
      currentTimeLabel.textContent = fmt(current);
      if (!isNaN(total)) totalTimeLabel.textContent = fmt(total);
      waveformContainer.setAttribute("aria-valuenow", Math.floor(current));
      const pct = total > 0 ? (current / total) * 100 : 0;
      progressOverlay.style.width = pct + "%";
      scrubHandle.style.left = pct + "%";
      activateWave(pct);
    }

    function loop() {
      if (isPlaying) {
        updateProgress();
        requestAnimationFrame(loop);
      }
    }

    function generateWaveform() {
      const bars = 70;
      for (let i = 0; i < bars; i++) {
        const bar = document.createElement("div");
        bar.className = "wave-bar";
        bar.style.height = Math.random() * 48 + 14 + "px";
        bar.style.animationDelay = i * 0.05 + "s";
        waveformEl.appendChild(bar);
      }
    }

    function resetWave() {
      waveformEl
        .querySelectorAll(".wave-bar")
        .forEach((b) => b.classList.remove("active"));
    }
    function activateWave(pct) {
      const bars = [...waveformEl.querySelectorAll(".wave-bar")];
      const active = Math.floor((pct / 100) * bars.length);
      bars.forEach((b, i) => b.classList.toggle("active", i < active));
    }

    function seekTo(clientX) {
      const rect = waveformContainer.getBoundingClientRect();
      let ratio = (clientX - rect.left) / rect.width;
      ratio = Math.min(1, Math.max(0, ratio));
      audioEl.currentTime =
        ratio * (audioEl.duration || episodes[currentIndex].duration);
      updateProgress();
    }

    waveformContainer.addEventListener("mousedown", (e) => {
      draggingSeek = true;
      seekTo(e.clientX);
      document.addEventListener("mousemove", onSeek);
      document.addEventListener("mouseup", endSeek);
    });
    function onSeek(e) {
      if (draggingSeek) seekTo(e.clientX);
    }
    function endSeek() {
      draggingSeek = false;
      document.removeEventListener("mousemove", onSeek);
      document.removeEventListener("mouseup", endSeek);
    }

    waveformContainer.addEventListener("keydown", (e) => {
      if (!audioEl.duration) return;
      if (["ArrowRight", "ArrowLeft", "Home", "End"].includes(e.key))
        e.preventDefault();
      const step = 5;
      if (e.key === "ArrowRight")
        audioEl.currentTime = Math.min(
          audioEl.currentTime + step,
          audioEl.duration
        );
      else if (e.key === "ArrowLeft")
        audioEl.currentTime = Math.max(audioEl.currentTime - step, 0);
      else if (e.key === "Home") audioEl.currentTime = 0;
      else if (e.key === "End") audioEl.currentTime = audioEl.duration;
      updateProgress();
    });

    function setVolume(v) {
      volume = Math.min(1, Math.max(0, v));
      audioEl.volume = volume;
      const pct = volume * 100;
      volumeFill.style.width = pct + "%";
      volumeHandle.style.left = pct + "%";
      volumeTrack.setAttribute("aria-valuenow", Math.round(pct));
      if (volume === 0) {
        isMuted = true;
      } else {
        isMuted = false;
        lastVolume = volume;
      }
      updateMuteIcon();
    }

    function updateMuteIcon() {
      const icon = playerWrapper.querySelector("#iconVolume");
      if (isMuted || volume === 0)
        icon.innerHTML =
          '<path d="M11 5 6 9H3v6h3l5 4V5Z" stroke-linecap="round" stroke-linejoin="round"/><path d="m16 9 4 6M20 9l-4 6" stroke-linecap="round" stroke-linejoin="round"/>';
      else if (volume < 0.5)
        icon.innerHTML =
          '<path d="M11 5 6 9H3v6h3l5 4V5Z" stroke-linecap="round" stroke-linejoin="round"/><path d="M15 10a2 2 0 0 1 0 4" stroke-linecap="round" stroke-linejoin="round"/>';
      else
        icon.innerHTML =
          '<path d="M11 5 6 9H3v6h3l5 4V5Z" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 9a3 3 0 0 1 0 6" stroke-linecap="round" stroke-linejoin="round"/>';
    }

    volumeTrack.addEventListener("mousedown", (e) => {
      draggingVolume = true;
      volDrag(e.clientX);
      document.addEventListener("mousemove", volMove);
      document.addEventListener("mouseup", volEnd);
    });
    function volDrag(x) {
      const rect = volumeTrack.getBoundingClientRect();
      let ratio = (x - rect.left) / rect.width;
      setVolume(ratio);
    }
    function volMove(e) {
      if (draggingVolume) volDrag(e.clientX);
    }
    function volEnd() {
      draggingVolume = false;
      document.removeEventListener("mousemove", volMove);
      document.removeEventListener("mouseup", volEnd);
    }
    volumeTrack.addEventListener("keydown", (e) => {
      if (
        [
          "ArrowRight",
          "ArrowUp",
          "ArrowLeft",
          "ArrowDown",
          "Home",
          "End",
        ].includes(e.key)
      )
        e.preventDefault();
      if (e.key === "ArrowRight" || e.key === "ArrowUp")
        setVolume(volume + 0.05);
      else if (e.key === "ArrowLeft" || e.key === "ArrowDown")
        setVolume(volume - 0.05);
      else if (e.key === "Home") setVolume(0);
      else if (e.key === "End") setVolume(1);
    });

    muteBtn.addEventListener("click", () => {
      if (isMuted || volume === 0) setVolume(lastVolume || 0.7);
      else {
        lastVolume = volume;
        setVolume(0);
      }
      isMuted = !isMuted;
      updateMuteIcon();
    });

    btnShuffle.addEventListener("click", () => {
      shuffleOn = !shuffleOn;
      btnShuffle.classList.toggle("active", shuffleOn);
      btnShuffle.setAttribute(
        "aria-label",
        `Shuffle (${shuffleOn ? "on" : "off"})`
      );
    });

    btnRepeat.addEventListener("click", () => {
      if (repeatMode === "off") {
        repeatMode = "all";
        btnRepeat.classList.add("active");
        btnRepeat.classList.remove("repeat-mode-one");
      } else if (repeatMode === "all") {
        repeatMode = "one";
        btnRepeat.classList.add("repeat-mode-one");
      } else {
        repeatMode = "off";
        btnRepeat.classList.remove("active", "repeat-mode-one");
      }
      btnRepeat.setAttribute("aria-label", `Repeat (${repeatMode})`);
    });

    playlistToggleBtn.addEventListener("click", () => {
      playlistOpen = !playlistOpen;
      playlistContainer.classList.toggle("open", playlistOpen);
      playlistToggleBtn.classList.toggle("open", playlistOpen);
      playlistToggleBtn.setAttribute("aria-expanded", String(playlistOpen));
    });

    playBtn.addEventListener("click", () =>
      isPlaying ? pauseAudio() : playAudio()
    );
    btnPrev.addEventListener("click", () => {
      if (shuffleOn) {
        let rand;
        do {
          rand = Math.floor(Math.random() * episodes.length);
        } while (rand === currentIndex && episodes.length > 1);
        loadEpisode(rand, true);
      } else {
        loadEpisode(
          (currentIndex - 1 + episodes.length) % episodes.length,
          true
        );
      }
    });

    function nextTrack() {
      if (shuffleOn) {
        let rand;
        do {
          rand = Math.floor(Math.random() * episodes.length);
        } while (rand === currentIndex && episodes.length > 1);
        loadEpisode(rand, true);
      } else {
        loadEpisode((currentIndex + 1) % episodes.length, true);
      }
    }
    btnNext.addEventListener("click", nextTrack);

    likeBtn.addEventListener("click", toggleLike);

    playerWrapper.querySelector("#btnShare").addEventListener("click", () => {
      const ep = episodes[currentIndex];
      if (navigator.share) {
        navigator
          .share({
            title: ep.title,
            text: "Check out this episode!",
            url: location.href,
          })
          .catch(() => {});
      } else {
        alert("Share not supported in this browser.");
      }
    });

    playerWrapper
      .querySelector("#btnDownload")
      .addEventListener("click", () => {
        const ep = episodes[currentIndex];
        const a = document.createElement("a");
        a.href = ep.src;
        a.download = ep.title.replace(/\s+/g, "_") + ".mp3";
        document.body.appendChild(a);
        a.click();
        a.remove();
      });

    audioEl.addEventListener("timeupdate", updateProgress);
    audioEl.addEventListener("loadedmetadata", () => {
      totalTimeLabel.textContent = fmt(audioEl.duration);
      waveformContainer.setAttribute(
        "aria-valuemax",
        Math.floor(audioEl.duration)
      );
    });
    audioEl.addEventListener("ended", () => {
      if (repeatMode === "one") {
        audioEl.currentTime = 0;
        playAudio();
      } else if (repeatMode === "all" || shuffleOn) {
        nextTrack();
      } else {
        if (currentIndex < episodes.length - 1) nextTrack();
        else pauseAudio();
      }
    });

    function applyTheme(dark) {
      if (playerWrapper) {
        playerWrapper.classList.toggle("dark-theme", dark);
      }
      themeToggleText.textContent = dark ? "Light Mode" : "Dark Mode";
      themeToggleIcon.innerHTML = dark
        ? `<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><path d="M21 12.79A9 9 0 0 1 11.21 3 7 7 0 1 0 21 12.79Z"/></svg>`
        : `<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path stroke-linecap="round" d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.07 7.07-1.42-1.42M8.35 8.35 6.93 6.93m0 10.14 1.42-1.42m9.3-9.3 1.42 1.42"/></svg>`;
      localStorage.setItem("casPlayerTheme", dark ? "dark" : "light");
    }
    themeToggleBtn.addEventListener("click", () =>
      applyTheme(!playerWrapper.classList.contains("dark-theme"))
    );

    (function initTheme() {
      const saved = localStorage.getItem("casPlayerTheme");
      const prefersDark =
        window.matchMedia &&
        window.matchMedia("(prefers-color-scheme: dark)").matches;
      if (saved) applyTheme(saved === "dark");
      else if (playerWrapper.classList.contains("dark-theme"))
        applyTheme(true); // From PHP
      else if (prefersDark) applyTheme(true);
    })();

    /* Init */
    generateWaveform();
    buildPlaylist();
    loadEpisode(0, false);
    setVolume(volume);
    updateMuteIcon();

    /* Auto demo play - REMOVED so it doesn't autoplay on every page load */
    // setTimeout(()=>playAudio(),500);
  };

  // Initialize all players on the page when the DOM is ready
  document.addEventListener("DOMContentLoaded", () => {
    const players = document.querySelectorAll(".cas-player-modern");
    players.forEach(initializePlayer);
  });
})();
