document.addEventListener("DOMContentLoaded", function () {

    /* ============================================================
       MATRIX RAIN (starts later)
    ============================================================ */
    const canvas = document.getElementById('matrix-canvas');
    const ctx = canvas.getContext('2d');

    const fontSize = 14;
    const chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZｱｲｳｴｵｶｷｸｹｺｻｼｽｾｿﾀﾁﾂﾃﾄﾅﾆﾇﾈﾉﾊﾋﾌﾍﾎﾏﾐﾑﾒﾓﾔﾕﾖﾗﾘﾙﾚﾛﾜﾝ";
    const charArr = chars.split('');
    let drops = [];

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        const columns = Math.floor(canvas.width / fontSize);
        drops = new Array(columns).fill(1);
    }

    function startMatrix() {
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        setInterval(() => {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = '#0F0';
            ctx.font = fontSize + 'px monospace';

            for (let i = 0; i < drops.length; i++) {
                const text = charArr[Math.floor(Math.random() * charArr.length)];
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);

                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }

                drops[i]++;
            }
        }, 33);
    }

    /* ============================================================
       INTRO SEQUENCE
    ============================================================ */
    const intro = document.getElementById("intro-text");
    const introBox = document.getElementById("intro-sequence");
    const formContainer = document.querySelector(".container");
    const skipBtn = document.getElementById("skip-intro");

    canvas.style.display = "none";

    const lines = [
        "Wake up, Nico...",
        "The Matrix has you...",
        "Follow the white rabbit...",
        "Knock, knock, Nico..."
    ];

    let lineIndex = 0;
    let charIndex = 0;
    let typingActive = true; // <--- NEW FLAG

    // Skip Intro Handler
    function skipIntro() {
        typingActive = false;

        skipBtn.style.display = "none";   // hide button immediately
        introBox.style.opacity = "0";

        setTimeout(() => {
            introBox.style.display = "none";

            canvas.style.display = "block";
            startMatrix();

            formContainer.style.opacity = "1";
        }, 500);
    }

    skipBtn.addEventListener("click", skipIntro);

    // Start intro after 3 seconds
    
    skipBtn.style.display = "none";
    
    setTimeout(() => {
        introBox.classList.add("show-intro");
        skipBtn.style.display = "block";
        typeLine();
    }, 3000);

    function typeLine() {
        if (!typingActive) return; // <--- STOP IF SKIPPED

        const currentLine = lines[lineIndex];

        if (charIndex < currentLine.length) {
            intro.textContent += currentLine.charAt(charIndex);
            charIndex++;
            setTimeout(typeLine, 120);
        } else {
            setTimeout(() => {
                if (!typingActive) return; // <--- STOP IF SKIPPED

                if (lineIndex < lines.length - 1) {
                    intro.textContent = "";
                    charIndex = 0;
                    lineIndex++;
                    typeLine();
                } else {
                    introBox.style.opacity = "0";

                    setTimeout(() => {
                        introBox.style.display = "none";

                        skipBtn.style.display = "none";

                        canvas.style.display = "block";
                        startMatrix();

                        formContainer.style.opacity = "1";

                    }, 1500);
                }
            }, 2000);
        }
    }

});
