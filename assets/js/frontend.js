jQuery(document).ready(function($) {
    $(".atm-modern-player").each(function() {
        const player = $(this);
        const audioElement = player.find(".atm-audio-element")[0];
        const playButton = player.find(".atm-play-button");
        const progressBar = player.find(".atm-progress-fill");
        const currentTimeSpan = player.find(".atm-current-time");
        const durationSpan = player.find(".atm-duration");
        const speedBtn = player.find(".atm-speed-btn");
        const volumeBtn = player.find(".atm-volume-btn");
        const downloadBtn = player.find(".atm-download-btn");
        const progressContainer = player.find(".atm-progress-container");
        
        let isPlaying = false, currentSpeed = 1, isMuted = false;
        
        if (audioElement.readyState < 1) {
            player.addClass("atm-loading");
        }

        playButton.on("click", function() {
            if (isPlaying) {
                audioElement.pause();
            } else {
                const playPromise = audioElement.play();
                if (playPromise !== undefined) {
                    playPromise.catch(error => console.error("Playback failed:", error));
                }
            }
        });

        audioElement.addEventListener("play", () => {
            playButton.addClass("playing");
            isPlaying = true;
        });

        audioElement.addEventListener("pause", () => {
            playButton.removeClass("playing");
            isPlaying = false;
        });

        audioElement.addEventListener("timeupdate", function() {
            if (audioElement.duration) {
                progressBar.css("width", (audioElement.currentTime / audioElement.duration) * 100 + "%");
                currentTimeSpan.text(formatTime(audioElement.currentTime));
            }
        });

        audioElement.addEventListener("loadedmetadata", function() {
            durationSpan.text(formatTime(audioElement.duration));
            player.removeClass("atm-loading");
        });

        audioElement.addEventListener("loadstart", () => player.addClass("atm-loading"));
        audioElement.addEventListener("canplaythrough", () => player.removeClass("atm-loading"));

        progressContainer.on("click", function(e) {
            if (audioElement.duration) {
                const rect = this.getBoundingClientRect();
                audioElement.currentTime = ((e.clientX - rect.left) / rect.width) * audioElement.duration;
            }
        });

        speedBtn.on("click", function() {
            const speeds = [1, 1.25, 1.5, 2];
            currentSpeed = speeds[(speeds.indexOf(currentSpeed) + 1) % speeds.length];
            audioElement.playbackRate = currentSpeed;
            speedBtn.text(currentSpeed + "x");
        });

        volumeBtn.on("click", function() {
            audioElement.muted = !audioElement.muted;
            isMuted = audioElement.muted;
            volumeBtn.html(`<span class="atm-volume-icon">${isMuted ? "🔇" : "🔊"}</span>`);
        });

        if (downloadBtn.length) {
            downloadBtn.on("click", function() {
                const link = document.createElement("a");
                const audioSrc = $(this).data("download-url");
                link.href = audioSrc;
                link.download = getFileName(audioSrc);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }

        audioElement.addEventListener("ended", function() {
            isPlaying = false;
            playButton.removeClass("playing");
            progressBar.css("width", "0%");
            currentTimeSpan.text("0:00");
        });
        
        audioElement.addEventListener("error", e => console.error("Audio error:", e));
        
        $(document).on("keydown", function(e) {
            if (player.is(":visible")) {
                if (e.which === 32) { e.preventDefault(); playButton.click(); }
                if (e.which === 37 && audioElement.duration) { audioElement.currentTime = Math.max(0, audioElement.currentTime - 10); }
                if (e.which === 39 && audioElement.duration) { audioElement.currentTime = Math.min(audioElement.duration, audioElement.currentTime + 10); }
            }
        });
        
        function formatTime(seconds) {
            if (isNaN(seconds)) return "0:00";
            const minutes = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return minutes + ":" + (secs < 10 ? "0" : "") + secs;
        }
        
        function getFileName(url) {
            const urlPath = url.split("/");
            return urlPath[urlPath.length - 1] || "podcast.mp3";
        }
        
        volumeBtn.html("<span class=\"atm-volume-icon\">🔊</span>");
    });
});