before do pip install, please follow this step

1. cd ai-service
2. python -m venv venv
3. .\venv\Scripts\activate (windows) / source venv/bin/activate (max/linux)
4. pip install fastapi uvicorn requests google-generativeai python-dotenv mysql-connector-python
5. uvicorn main:app --reload --port 8001