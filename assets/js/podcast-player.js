// Content AI Studio — Podcast Player (backend icon set)
// No theme bleed. Handles: play/pause, seek, prev/next, shuffle, loop,
// volume, speed, download/share, like, playlist toggle and selection.
(function () {
  function qs(s, el = document) {
    return el.querySelector(s);
  }
  function qsa(s, el = document) {
    return Array.from(el.querySelectorAll(s));
  }
  function fmt(t) {
    const m = Math.floor(t / 60),
      s = Math.floor(t % 60);
    return m + ":" + (s < 10 ? "0" : "") + s;
  }

  qsa(".atm-podcast").forEach((root) => {
    const card = qs(".atm-card", root);
    if (!card) return;

    const audio = qs(".atm-audio", card);
    const playBtn = qs(".atm-play-btn", card);
    const playUse = playBtn?.querySelector("use");
    const prevBtn =
      qs(".atm-prev", card) || qs('.atm-ctrl[aria-label^="Previous"]', card);
    const nextBtn =
      qs(".atm-next", card) || qs('.atm-ctrl[aria-label^="Next"]', card);
    const loopBtn = qs(".atm-loop", card);
    const shufBtn = qs(".atm-shuffle", card);
    const likeBtn = qs(".atm-like", card);
    const shareBtn = qs(".atm-share", card);
    const dlBtn = qs(".atm-download", card);
    const rail = qs(".atm-rail", card);
    const fill = qs(".atm-rail-fill", card);
    const knob = qs(".atm-rail-knob", card);
    const tl = qs(".atm-tl", card);
    const tr = qs(".atm-tr", card);
    const vol = qs(".atm-vol-range", card);
    const volFill = qs(".atm-vol-fill", card);
    const volVal = qs(".atm-vol-val", card);
    const speedSel = qs(".atm-speed-select", card);
    const plToggle = qs(".atm-pl-toggle", card);
    const playlist = qs(".atm-playlist", card);
    const plItems = qsa(".atm-pl-item", card);

    // Build playlist: index 0 = current track (from audio src), then items from list
    let queue = [];
    const currentTrack = {
      url: audio?.getAttribute("src") || "",
      title: card.getAttribute("data-current-title") || document.title,
      cover: card.getAttribute("data-current-cover") || "",
    };
    queue.push(currentTrack);

    plItems.forEach((li) => {
      queue.push({
        url: li.getAttribute("data-url") || "",
        title:
          li.getAttribute("data-title") ||
          qs(".atm-pl-title", li)?.textContent ||
          "",
        cover: qs("img", li)?.getAttribute("src") || "",
      });
    });

    let idx = 0; // current queue index
    let shuffle = false;

    // Init times
    if (tr && audio?.duration) tr.textContent = fmt(audio.duration);

    // Play/Pause
    function setPlaying(on) {
      if (!audio) return;
      if (on) {
        audio.play().catch(() => {});
        playUse?.setAttribute("href", "#atm-i-pause");
      } else {
        audio.pause();
        playUse?.setAttribute("href", "#atm-i-play");
      }
    }

    playBtn?.addEventListener("click", () => setPlaying(audio?.paused));

    // Seek
    rail?.addEventListener("click", (e) => {
      if (!audio || !audio.duration) return;
      const rect = rail.getBoundingClientRect();
      const p = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
      audio.currentTime = audio.duration * p;
    });

    audio?.addEventListener("timeupdate", () => {
      if (!audio.duration) return;
      const pct = (audio.currentTime / audio.duration) * 100;
      if (fill) fill.style.width = pct + "%";
      if (knob) knob.style.left = pct + "%";
      if (tl) tl.textContent = fmt(audio.currentTime);
      if (tr && tr.textContent === "0:00") tr.textContent = fmt(audio.duration);
    });

    audio?.addEventListener("loadedmetadata", () => {
      if (tr) tr.textContent = fmt(audio.duration || 0);
    });

    // Volume
    const setVolumeUI = (v) => {
      if (!volFill || !volVal) return;
      volFill.style.width = v + "%";
      volVal.textContent = v + "%";
    };
    if (vol) {
      const start = parseInt(vol.value, 10) || 75;
      audio.volume = Math.max(0, Math.min(1, start / 100));
      setVolumeUI(start);
      vol.addEventListener("input", (e) => {
        const v = parseInt(e.target.value, 10);
        audio.volume = Math.max(0, Math.min(1, v / 100));
        setVolumeUI(v);
      });
    }

    // Speed
    speedSel?.addEventListener("change", (e) => {
      const val = parseFloat(String(e.target.value).replace("x", "")) || 1;
      audio.playbackRate = val;
    });

    // Download current track
    (qs(".atm-download", card) || dlBtn)?.addEventListener("click", () => {
      const a = document.createElement("a");
      a.href = queue[idx].url;
      a.download = "";
      document.body.appendChild(a);
      a.click();
      a.remove();
    });

    // Share current track
    (qs(".atm-share", card) || shareBtn)?.addEventListener(
      "click",
      async () => {
        try {
          if (navigator.share)
            await navigator.share({
              title: queue[idx].title,
              url: window.location.href,
            });
          else {
            await navigator.clipboard.writeText(window.location.href);
            alert("Link copied to clipboard");
          }
        } catch (e) {}
      }
    );

    // Like toggle (visual)
    likeBtn?.addEventListener("click", () => {
      likeBtn.classList.toggle("is-on");
      const use = likeBtn.querySelector("use");
      use?.setAttribute(
        "href",
        likeBtn.classList.contains("is-on")
          ? "#atm-i-heart-fill"
          : "#atm-i-heart"
      );
    });

    // Loop, Shuffle
    loopBtn?.addEventListener("click", () => loopBtn.classList.toggle("is-on"));
    shufBtn?.addEventListener("click", () => {
      shuffle = !shuffle;
      shufBtn.classList.toggle("is-on", shuffle);
    });

    // Prev/Next logic
    // Enhanced playIndex function with loading states
    function playIndex(i) {
      idx = i;
      const track = queue[idx];
      if (!track || !track.url) return;

      // Add loading state
      const titleElement = qs(".atm-head-title", card);
      if (titleElement) {
        titleElement.style.opacity = "0.6";
      }

      audio.src = track.url;
      audio.currentTime = 0;

      // Update UI elements
      setTimeout(() => {
        if (titleElement) {
          titleElement.textContent = track.title;
          titleElement.style.opacity = "1";
        }

        const episodeBadge = qs(".atm-ep", card);
        if (episodeBadge && track.cover) {
          episodeBadge.style.setProperty("--cover", `url('${track.cover}')`);
        }
      }, 150);

      const d = qs(".atm-download", card);
      if (d) d.setAttribute("data-download-url", track.url);

      setPlaying(true);
    }

    prevBtn?.addEventListener("click", () => {
      if (!audio) return;
      if (audio.currentTime > 5) {
        audio.currentTime = 0;
        return;
      }
      const nextIdx = (idx - 1 + queue.length) % queue.length;
      playIndex(nextIdx);
    });

    nextBtn?.addEventListener("click", () => {
      let nextIdx = idx + 1;
      if (shuffle) nextIdx = Math.floor(Math.random() * queue.length);
      if (nextIdx >= queue.length) nextIdx = 0;
      playIndex(nextIdx);
    });

    audio?.addEventListener("ended", () => {
      const loopOn = loopBtn?.classList.contains("is-on");
      if (loopOn) {
        audio.currentTime = 0;
        setPlaying(true);
        return;
      }
      // advance respecting shuffle
      nextBtn?.click();
    });

    // Playlist toggle + click
    plToggle?.addEventListener("click", () => {
      if (!playlist) return;
      const hidden = playlist.hasAttribute("hidden");
      if (hidden) playlist.removeAttribute("hidden");
      else playlist.setAttribute("hidden", "");
    });

    plItems.forEach((li, i) => {
      // i-th li corresponds to queue index i+1 (0 is current)
      li.addEventListener("click", () => playIndex(i + 1));
    });

    // Initial UI
    if (playUse && audio?.paused) playUse.setAttribute("href", "#atm-i-play");
    if (tr && audio?.duration) tr.textContent = fmt(audio.duration);
  });
})();
