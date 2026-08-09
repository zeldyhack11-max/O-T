from flask import Flask

app = Flask(__name__)

@app.route("/")
def home():
    return """
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Zeldy Site</title>
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                font-family: Arial, sans-serif;
                background: #0f172a;
                color: white;
                min-height: 100vh;
            }

            nav {
                padding: 20px 8%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #111827;
            }

            .logo {
                font-size: 25px;
                font-weight: bold;
                color: #38bdf8;
            }

            .hero {
                min-height: 80vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                text-align: center;
                padding: 30px;
            }

            h1 {
                font-size: 55px;
                margin-bottom: 20px;
            }

            h1 span {
                color: #38bdf8;
            }

            p {
                color: #94a3b8;
                font-size: 18px;
                max-width: 600px;
                margin-bottom: 30px;
            }

            .button {
                display: inline-block;
                padding: 14px 25px;
                background: #38bdf8;
                color: #020617;
                text-decoration: none;
                border-radius: 10px;
                font-weight: bold;
                transition: .2s;
            }

            .button:hover {
                transform: scale(1.05);
                background: #7dd3fc;
            }

            footer {
                text-align: center;
                padding: 20px;
                color: #64748b;
            }
        </style>
    </head>

    <body>

        <nav>
            <div class="logo">ZELDY</div>
            <div>Python • Flask</div>
        </nav>

        <section class="hero">
            <h1>Merhaba <span>Dünya!</span></h1>

            <p>
                Bu site Python ve Flask kullanılarak Alwaysdata
                sunucusunda çalışıyor.
            </p>

            <a class="button" href="/about">Hakkımda</a>
        </section>

        <footer>
            © 2026 Zeldy
        </footer>

    </body>
    </html>
    """

@app.route("/about")
def about():
    return """
    <h1 style="font-family:Arial;text-align:center;margin-top:100px">
        Python Flask ile çalışıyor 🚀
    </h1>
    """

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
