from flask import Flask, render_template
import os
from dotenv import load_dotenv

# Cargar variables de entorno
load_dotenv()

def create_app():
    """Crea y configura la aplicación Flask"""
    
    print("📦 Creando aplicación Flask...")
    
    # Crear aplicación Flask
    app = Flask(__name__,
                template_folder='templates',
                static_folder='static')
    
    # Configuración básica
    app.config['SECRET_KEY'] = os.getenv('SECRET_KEY', 'clave_secreta_por_defecto')
    app.config['DEBUG'] = os.getenv('FLASK_ENV') == 'development'
    
    print("✅ Configuración cargada")
    
    # Ruta principal
    @app.route('/')
    def index():
        return render_template('index.html')
    
    # Ruta de login
    @app.route('/login')
    def login():
        return render_template('auth/login.html')
    
    # Ruta de saludo (para pruebas)
    @app.route('/saludo/<nombre>')
    def saludo(nombre):
        return f'<h1>Hola {nombre}!</h1><p>Bienvenido al sistema de nómina Venezuela</p>'
    
    return app