// Content AI Studio – Modern Podcast Player JavaScript
// Matches your original design with all functionality + settings integration

(function () {
  "use strict";

  // Initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializePodcastPlayer);
  } else {
    initializePodcastPlayer();
  }

  function initializePodcastPlayer() {
    const players = document.querySelectorAll(".podcast-player");
    players.forEach(initializePlayer);
  }

  function initializePlayer(playerElement) {
    // Get all DOM elements
    const elements = getPlayerElements(playerElement);
    if (!elements.audioEl) return;

    // Initialize player state
    const state = initializePlayerState();

    // Apply theme and colors from settings
    applyThemeSettings(playerElement);

    // Generate waveform
    generateWaveform(elements.waveform);

    // Set up all event listeners
    setupEventListeners(elements, state);

    // Build playlist from existing items
    buildPlaylist(elements, state);

    // Set initial volume
    setVolume(elements, state, state.volume);
    updateMuteIcon(elements, state);

    // Auto-play demo (like original)
    setTimeout(() => playAudio(elements, state), 500);
  }

  function getPlayerElements(player) {
    return {
      audioEl: player.querySelector("#audioEl"),
      playBtn: player.querySelector("#btnPlayPause"),
      iconPlay: player.querySelector("#iconPlay"),
      iconPause: player.querySelector("#iconPause"),
      currentTimeLabel: player.querySelector("#currentTimeLabel"),
      totalTimeLabel: player.querySelector("#totalTimeLabel"),
      waveformContainer: player.querySelector("#waveformContainer"),
      waveform: player.querySelector("#waveform"),
      progressOverlay: player.querySelector("#progressOverlay"),
      scrubHandle: player.querySelector("#scrubHandle"),
      volumeTrack: player.querySelector("#volumeTrack"),
      volumeFill: player.querySelector("#volumeFill"),
      volumeHandle: player.querySelector("#volumeHandle"),
      muteBtn: player.querySelector("#btnMute"),
      iconVolume: player.querySelector("#iconVolume"),
      likeBtn: player.querySelector("#btnLike"),
      heartOutline: player.querySelector("#iconHeartOutline"),
      heartFilled: player.querySelector("#iconHeartFilled"),
      shareBtn: player.querySelector("#btnShare"),
      downloadBtn: player.querySelector("#btnDownload"),
      playlistToggleBtn: player.querySelector("#playlistToggleBtn"),
      playlistContainer: player.querySelector("#playlistContainer"),
      playlistList: player.querySelector("#playlistList"),
      episodeTitle: player.querySelector("#episodeTitle"),
      episodeAuthor: player.querySelector("#episodeAuthor"),
      artworkPulse: player.querySelector("#artworkPulse"),
      btnPrev: player.querySelector("#btnPrev"),
      btnNext: player.querySelector("#btnNext"),
      btnShuffle: player.querySelector("#btnShuffle"),
      btnRepeat: player.querySelector("#btnRepeat"),
      waveformContainer: player.querySelector("#waveformContainer"),
    };
  }

  function initializePlayerState() {
    return {
      currentIndex: 0,
      isPlaying: false,
      isMuted: false,
      isLiked: false,
      draggingSeek: false,
      draggingVolume: false,
      volume: 0.7,
      lastVolume: 0.7,
      speed: 1,
      playlistOpen: false,
      shuffleOn: false,
      repeatMode: "off", // 'off' | 'all' | 'one'
      episodes: [],
    };
  }

  function applyThemeSettings(player) {
    // Apply dynamic CSS variables from settings
    if (typeof atm_podcast_settings !== "undefined") {
      const settings = atm_podcast_settings;
      const theme = settings.theme || "light";

      // Set theme attribute
      player.setAttribute("data-theme", theme);

      // Create dynamic styles
      const dynamicStyles = document.createElement("style");
      dynamicStyles.id =
        "atm-podcast-dynamic-" + Math.random().toString(36).substr(2, 9);

      let css = ":root {";
      css += `--color-accent: ${settings.accent_color || "#2979ff"};`;
      css += `--color-accent-hover: ${settings.gradient_end || "#1d63d6"};`;
      css += "}";

      if (theme === "light") {
        css += '.podcast-player[data-theme="light"] {';
        css += `--color-bg-alt: ${settings.light_card_bg || "#ffffff"};`;
        css += `--color-bg-alt2: ${settings.light_bg_alt || "#f9fafb"};`;
        css += `--color-border: ${settings.light_border || "#dfe3e8"};`;
        css += `--color-text: ${settings.light_text || "#1f2933"};`;
        css += `--color-text-soft: ${settings.light_subtext || "#616d79"};`;
        css += "}";
      } else {
        css += '.podcast-player[data-theme="dark"] {';
        css += `--color-bg-alt: ${settings.dark_card_bg || "#1f2732"};`;
        css += `--color-bg-alt2: ${settings.dark_bg_alt || "#1a2330"};`;
        css += `--color-border: ${settings.dark_border || "#2b3541"};`;
        css += `--color-text: ${settings.dark_text || "#f2f6fa"};`;
        css += `--color-text-soft: ${settings.dark_subtext || "#a5b1bc"};`;
        css += "}";
      }

      dynamicStyles.textContent = css;
      document.head.appendChild(dynamicStyles);
    }
  }

  function setupEventListeners(elements, state) {
    // Play/Pause
    if (elements.playBtn) {
      elements.playBtn.addEventListener("click", () => {
        if (state.isPlaying) {
          pauseAudio(elements, state);
        } else {
          playAudio(elements, state);
        }
      });
    }

    // Audio events
    if (elements.audioEl) {
      elements.audioEl.addEventListener("timeupdate", () =>
        updateProgress(elements, state)
      );
      elements.audioEl.addEventListener("loadedmetadata", () => {
        if (elements.totalTimeLabel) {
          elements.totalTimeLabel.textContent = formatTime(
            elements.audioEl.duration
          );
        }
        elements.waveformContainer?.setAttribute(
          "aria-valuemax",
          Math.floor(elements.audioEl.duration)
        );
      });
      elements.audioEl.addEventListener("ended", () =>
        handleAudioEnd(elements, state)
      );
    }

    // Seek functionality
    if (elements.waveformContainer) {
      elements.waveformContainer.addEventListener("mousedown", (e) => {
        state.draggingSeek = true;
        seekTo(elements, state, e.clientX);
        document.addEventListener("mousemove", onSeek);
        document.addEventListener("mouseup", endSeek);
      });

      // Keyboard seek
      elements.waveformContainer.addEventListener("keydown", (e) => {
        if (!elements.audioEl.duration) return;
        if (["ArrowRight", "ArrowLeft", "Home", "End"].includes(e.key)) {
          e.preventDefault();
        }
        const step = 5;
        if (e.key === "ArrowRight") {
          elements.audioEl.currentTime = Math.min(
            elements.audioEl.currentTime + step,
            elements.audioEl.duration
          );
        } else if (e.key === "ArrowLeft") {
          elements.audioEl.currentTime = Math.max(
            elements.audioEl.currentTime - step,
            0
          );
        } else if (e.key === "Home") {
          elements.audioEl.currentTime = 0;
        } else if (e.key === "End") {
          elements.audioEl.currentTime = elements.audioEl.duration;
        }
        updateProgress(elements, state);
      });
    }

    // Volume controls
    if (elements.volumeTrack) {
      elements.volumeTrack.addEventListener("mousedown", (e) => {
        state.draggingVolume = true;
        volumeDrag(elements, state, e.clientX);
        document.addEventListener("mousemove", volumeMove);
        document.addEventListener("mouseup", volumeEnd);
      });

      elements.volumeTrack.addEventListener("keydown", (e) => {
        if (
          [
            "ArrowRight",
            "ArrowUp",
            "ArrowLeft",
            "ArrowDown",
            "Home",
            "End",
          ].includes(e.key)
        ) {
          e.preventDefault();
        }
        if (e.key === "ArrowRight" || e.key === "ArrowUp") {
          setVolume(elements, state, state.volume + 0.05);
        } else if (e.key === "ArrowLeft" || e.key === "ArrowDown") {
          setVolume(elements, state, state.volume - 0.05);
        } else if (e.key === "Home") {
          setVolume(elements, state, 0);
        } else if (e.key === "End") {
          setVolume(elements, state, 1);
        }
      });
    }

    // Mute button
    if (elements.muteBtn) {
      elements.muteBtn.addEventListener("click", () => {
        if (state.isMuted || state.volume === 0) {
          setVolume(elements, state, state.lastVolume || 0.7);
          state.isMuted = false;
        } else {
          state.lastVolume = state.volume;
          setVolume(elements, state, 0);
          state.isMuted = true;
        }
        updateMuteIcon(elements, state);
      });
    }

    // Control buttons
    if (elements.btnPrev) {
      elements.btnPrev.addEventListener("click", () => {
        if (state.shuffleOn) {
          let rand;
          do {
            rand = Math.floor(Math.random() * state.episodes.length);
          } while (rand === state.currentIndex && state.episodes.length > 1);
          loadEpisode(elements, state, rand, true);
        } else {
          loadEpisode(
            elements,
            state,
            (state.currentIndex - 1 + state.episodes.length) %
              state.episodes.length,
            true
          );
        }
      });
    }

    if (elements.btnNext) {
      elements.btnNext.addEventListener("click", () => {
        nextTrack(elements, state);
      });
    }

    if (elements.btnShuffle) {
      elements.btnShuffle.addEventListener("click", () => {
        state.shuffleOn = !state.shuffleOn;
        elements.btnShuffle.classList.toggle("active", state.shuffleOn);
        elements.btnShuffle.setAttribute(
          "aria-label",
          `Shuffle (${state.shuffleOn ? "on" : "off"})`
        );
      });
    }

    if (elements.btnRepeat) {
      elements.btnRepeat.addEventListener("click", () => {
        if (state.repeatMode === "off") {
          state.repeatMode = "all";
          elements.btnRepeat.classList.add("active");
          elements.btnRepeat.classList.remove("repeat-mode-one");
        } else if (state.repeatMode === "all") {
          state.repeatMode = "one";
          elements.btnRepeat.classList.add("repeat-mode-one");
        } else {
          state.repeatMode = "off";
          elements.btnRepeat.classList.remove("active", "repeat-mode-one");
        }
        elements.btnRepeat.setAttribute(
          "aria-label",
          `Repeat (${state.repeatMode})`
        );
      });
    }

    // Like button
    if (elements.likeBtn) {
      elements.likeBtn.addEventListener("click", () =>
        toggleLike(elements, state)
      );
    }

    // Share button
    if (elements.shareBtn) {
      elements.shareBtn.addEventListener("click", () => {
        const ep = state.episodes[state.currentIndex];
        if (navigator.share) {
          navigator
            .share({
              title:
                ep?.title ||
                elements.episodeTitle?.textContent ||
                document.title,
              text: "Check out this episode!",
              url: location.href,
            })
            .catch(() => {});
        } else {
          alert("Share not supported in this browser.");
        }
      });
    }

    // Download button
    if (elements.downloadBtn) {
      elements.downloadBtn.addEventListener("click", () => {
        const ep = state.episodes[state.currentIndex];
        const url = ep?.url || elements.audioEl?.src;
        if (url) {
          const a = document.createElement("a");
          a.href = url;
          a.download = (ep?.title || "podcast").replace(/\s+/g, "_") + ".mp3";
          document.body.appendChild(a);
          a.click();
          a.remove();
        }
      });
    }

    // Playlist toggle
    if (elements.playlistToggleBtn) {
      elements.playlistToggleBtn.addEventListener("click", () => {
        state.playlistOpen = !state.playlistOpen;
        if (elements.playlistContainer) {
          elements.playlistContainer.classList.toggle(
            "open",
            state.playlistOpen
          );
        }
        elements.playlistToggleBtn.classList.toggle("open", state.playlistOpen);
        elements.playlistToggleBtn.setAttribute(
          "aria-expanded",
          String(state.playlistOpen)
        );
      });
    }

    // Keyboard shortcuts
    document.addEventListener("keydown", (e) => {
      if (e.target.matches('input,textarea,[contenteditable="true"]')) return;
      if (e.code === "Space") {
        e.preventDefault();
        if (state.isPlaying) {
          pauseAudio(elements, state);
        } else {
          playAudio(elements, state);
        }
      } else if (e.key === "ArrowRight") {
        elements.audioEl.currentTime = Math.min(
          elements.audioEl.currentTime + 5,
          elements.audioEl.duration || elements.audioEl.currentTime + 5
        );
        updateProgress(elements, state);
      } else if (e.key === "ArrowLeft") {
        elements.audioEl.currentTime = Math.max(
          elements.audioEl.currentTime - 5,
          0
        );
        updateProgress(elements, state);
      } else if (e.key === "ArrowUp") {
        setVolume(elements, state, state.volume + 0.05);
      } else if (e.key === "ArrowDown") {
        setVolume(elements, state, state.volume - 0.05);
      } else if (e.key === "l" || e.key === "L") {
        toggleLike(elements, state);
      }
    });

    // Helper functions for event listeners
    function onSeek(e) {
      if (state.draggingSeek) seekTo(elements, state, e.clientX);
    }

    function endSeek() {
      state.draggingSeek = false;
      document.removeEventListener("mousemove", onSeek);
      document.removeEventListener("mouseup", endSeek);
    }

    function volumeMove(e) {
      if (state.draggingVolume) volumeDrag(elements, state, e.clientX);
    }

    function volumeEnd() {
      state.draggingVolume = false;
      document.removeEventListener("mousemove", volumeMove);
      document.removeEventListener("mouseup", volumeEnd);
    }
  }

  function buildPlaylist(elements, state) {
    // Build episodes array from current track + playlist items
    state.episodes = [];

    // Current track (from audio element)
    const currentTrack = {
      title: elements.episodeTitle?.textContent || document.title,
      url: elements.audioEl?.getAttribute("src") || "",
      author: elements.episodeAuthor?.textContent || "Content AI Studio",
    };
    state.episodes.push(currentTrack);

    // Add playlist items
    if (elements.playlistList) {
      const playlistItems =
        elements.playlistList.querySelectorAll(".playlist-item");
      playlistItems.forEach((item, index) => {
        const episode = {
          title:
            item.getAttribute("data-title") ||
            item.querySelector(".playlist-title")?.textContent ||
            "",
          url: item.getAttribute("data-url") || "",
          author:
            item.querySelector(".playlist-author")?.textContent ||
            "Content AI Studio",
        };
        state.episodes.push(episode);

        // Add click handler
        item.addEventListener("click", () => {
          loadEpisode(elements, state, index + 1, true);
        });
      });
    }

    highlightPlaylist(elements, state);
  }

  function highlightPlaylist(elements, state) {
    if (!elements.playlistList) return;

    const playlistItems =
      elements.playlistList.querySelectorAll(".playlist-item");
    playlistItems.forEach((item, index) => {
      // index + 1 because current episode is at index 0
      item.classList.toggle("active", index + 1 === state.currentIndex);
    });
  }

  function loadEpisode(elements, state, index, autoplay = false) {
    state.currentIndex = index;
    const episode = state.episodes[index];
    if (!episode || !episode.url) return;

    elements.audioEl.src = episode.url;
    elements.audioEl.playbackRate = state.speed;

    if (elements.episodeTitle) {
      elements.episodeTitle.textContent = episode.title;
    }
    if (elements.episodeAuthor) {
      elements.episodeAuthor.textContent = episode.author;
    }

    elements.waveformContainer?.setAttribute(
      "aria-valuemax",
      elements.audioEl.duration || 0
    );
    highlightPlaylist(elements, state);
    resetWaveform(elements);

    if (autoplay) {
      playAudio(elements, state);
    } else {
      pauseAudio(elements, state);
    }
  }

  function playAudio(elements, state) {
    if (!elements.audioEl) return;

    elements.audioEl
      .play()
      .then(() => {
        state.isPlaying = true;
        if (elements.iconPlay) elements.iconPlay.style.display = "none";
        if (elements.iconPause) elements.iconPause.style.display = "block";
        elements.playBtn?.setAttribute("aria-label", "Pause");
        elements.waveform?.classList.add("playing");
        if (elements.artworkPulse) {
          elements.artworkPulse.style.animationPlayState = "running";
        }
      })
      .catch((e) => console.warn("Playback failed:", e));
  }

  function pauseAudio(elements, state) {
    if (!elements.audioEl) return;

    elements.audioEl.pause();
    state.isPlaying = false;
    if (elements.iconPlay) elements.iconPlay.style.display = "block";
    if (elements.iconPause) elements.iconPause.style.display = "none";
    elements.playBtn?.setAttribute("aria-label", "Play");
    elements.waveform?.classList.remove("playing");
    if (elements.artworkPulse) {
      elements.artworkPulse.style.animationPlayState = "paused";
    }
  }

  function toggleLike(elements, state) {
    state.isLiked = !state.isLiked;
    elements.likeBtn?.classList.toggle("liked", state.isLiked);
    if (elements.heartOutline) {
      elements.heartOutline.style.display = state.isLiked ? "none" : "block";
    }
    if (elements.heartFilled) {
      elements.heartFilled.style.display = state.isLiked ? "block" : "none";
    }
    elements.likeBtn?.setAttribute("aria-pressed", String(state.isLiked));
  }

  function updateProgress(elements, state) {
    if (!elements.audioEl) return;

    const current = elements.audioEl.currentTime;
    const total =
      elements.audioEl.duration ||
      state.episodes[state.currentIndex]?.duration ||
      0;

    if (elements.currentTimeLabel) {
      elements.currentTimeLabel.textContent = formatTime(current);
    }
    if (elements.totalTimeLabel) {
      elements.totalTimeLabel.textContent = formatTime(total);
    }

    elements.waveformContainer?.setAttribute(
      "aria-valuenow",
      Math.floor(current)
    );

    const percentage = total > 0 ? (current / total) * 100 : 0;
    if (elements.progressOverlay) {
      elements.progressOverlay.style.width = percentage + "%";
    }
    if (elements.scrubHandle) {
      elements.scrubHandle.style.left = percentage + "%";
    }

    activateWaveform(elements, percentage);
  }

  function seekTo(elements, state, clientX) {
    if (!elements.waveformContainer || !elements.audioEl) return;

    const rect = elements.waveformContainer.getBoundingClientRect();
    let ratio = (clientX - rect.left) / rect.width;
    ratio = Math.min(1, Math.max(0, ratio));

    const duration =
      elements.audioEl.duration ||
      state.episodes[state.currentIndex]?.duration ||
      0;
    elements.audioEl.currentTime = ratio * duration;
    updateProgress(elements, state);
  }

  function setVolume(elements, state, volume) {
    state.volume = Math.min(1, Math.max(0, volume));
    if (elements.audioEl) {
      elements.audioEl.volume = state.volume;
    }

    const percentage = state.volume * 100;
    if (elements.volumeFill) {
      elements.volumeFill.style.width = percentage + "%";
    }
    if (elements.volumeHandle) {
      elements.volumeHandle.style.left = percentage + "%";
    }
    elements.volumeTrack?.setAttribute("aria-valuenow", Math.round(percentage));

    if (state.volume === 0) {
      state.isMuted = true;
    } else {
      state.isMuted = false;
      state.lastVolume = state.volume;
    }
    updateMuteIcon(elements, state);
  }

  function volumeDrag(elements, state, clientX) {
    if (!elements.volumeTrack) return;

    const rect = elements.volumeTrack.getBoundingClientRect();
    let ratio = (clientX - rect.left) / rect.width;
    setVolume(elements, state, ratio);
  }

  function updateMuteIcon(elements, state) {
    if (!elements.iconVolume) return;

    if (state.isMuted || state.volume === 0) {
      elements.iconVolume.innerHTML = '<use href="#atm-volume-mute"></use>';
    } else {
      elements.iconVolume.innerHTML = '<use href="#atm-volume"></use>';
    }
  }

  function nextTrack(elements, state) {
    if (state.shuffleOn) {
      let randomIndex;
      do {
        randomIndex = Math.floor(Math.random() * state.episodes.length);
      } while (randomIndex === state.currentIndex && state.episodes.length > 1);
      loadEpisode(elements, state, randomIndex, true);
    } else {
      loadEpisode(
        elements,
        state,
        (state.currentIndex + 1) % state.episodes.length,
        true
      );
    }
  }

  function handleAudioEnd(elements, state) {
    if (state.repeatMode === "one") {
      elements.audioEl.currentTime = 0;
      playAudio(elements, state);
    } else if (state.repeatMode === "all" || state.shuffleOn) {
      nextTrack(elements, state);
    } else {
      if (state.currentIndex < state.episodes.length - 1) {
        nextTrack(elements, state);
      } else {
        pauseAudio(elements, state);
      }
    }
  }

  function generateWaveform(waveformElement) {
    if (!waveformElement) return;

    const bars = 70;
    waveformElement.innerHTML = ""; // Clear existing bars

    for (let i = 0; i < bars; i++) {
      const bar = document.createElement("div");
      bar.className = "wave-bar";
      bar.style.height = Math.random() * 48 + 14 + "px";
      bar.style.animationDelay = i * 0.05 + "s";
      waveformElement.appendChild(bar);
    }
  }

  function resetWaveform(elements) {
    if (!elements.waveform) return;

    const bars = elements.waveform.querySelectorAll(".wave-bar");
    bars.forEach((bar) => bar.classList.remove("active"));
  }

  function activateWaveform(elements, percentage) {
    if (!elements.waveform) return;

    const bars = elements.waveform.querySelectorAll(".wave-bar");
    const activeCount = Math.floor((percentage / 100) * bars.length);

    bars.forEach((bar, index) => {
      bar.classList.toggle("active", index < activeCount);
    });
  }

  function formatTime(seconds) {
    if (isNaN(seconds)) return "0:00";
    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return minutes + ":" + (secs < 10 ? "0" : "") + secs;
  }
})();
