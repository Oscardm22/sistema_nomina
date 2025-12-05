from app import create_app

app = create_app()

if __name__ == '__main__':
    print("=" * 50)
    print("🚀 SISTEMA DE NÓMINA VENEZUELA")
    print("=" * 50)
    print("🌐 Servidor: http://localhost:5000")
    print("=" * 50)
    app.run(debug=True, port=5000)