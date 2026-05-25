import sys
import os

# Tambahkan root ai-service ke sys.path agar `from main import ...` bisa resolve
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
