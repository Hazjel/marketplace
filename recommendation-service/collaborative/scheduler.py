import asyncio

from collaborative.svd import train
from config import CF_RETRAIN_INTERVAL_HOURS
from utils.interactions import fetch_interactions
from utils.metrics import CF_MODEL_TRAINED, CF_TRAINING_INTERACTIONS


async def retrain_once() -> bool:
    interactions = await fetch_interactions()
    CF_TRAINING_INTERACTIONS.set(len(interactions))
    trained = train(interactions)
    CF_MODEL_TRAINED.set(1 if trained else 0)
    return trained


async def cf_retrain_loop() -> None:
    """Background task: retrain model SVD berkala dari data interaksi terbaru."""
    while True:
        try:
            await retrain_once()
        except Exception as e:
            print(f"[CF] Retrain loop error: {e}")
        await asyncio.sleep(CF_RETRAIN_INTERVAL_HOURS * 3600)
