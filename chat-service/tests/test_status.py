from utils.context import build_product_context


# ---------------------------------------------------------------------------
# build_product_context
#
# Test lama di file ini menguji _parse_classifier_output dan
# _build_product_context di main.py, yang sudah tidak ada sama sekali --
# deteksi intent LLM-classifier ("YA|keyword"/"TIDAK|none") sudah diganti
# pendekatan regex deterministik (nlp/intent.py), dan build_product_context
# pindah ke utils/context.py dengan perilaku yang juga berubah (harga
# sengaja tidak lagi diinjeksi ke context string -- sudah tampil otomatis
# sebagai kartu di UI, lihat komentar di utils/context.py). Ditulis ulang
# untuk menguji perilaku yang benar-benar berjalan sekarang, bukan
# mempertahankan asersi lama yang sudah tidak sesuai kode.
# ---------------------------------------------------------------------------
class TestBuildProductContext:
    def test_empty_products_returns_empty_string(self):
        # Pertanyaan umum/non-produk sengaja tidak inject apa pun ke LLM.
        assert build_product_context([]) == ""

    def test_products_list_included_in_context(self):
        products = [
            {
                "name": "ROG Phone 8",
                "category": "Smartphone",
                "condition": "Baru",
            }
        ]
        result = build_product_context(products)
        assert "ROG Phone 8" in result
        assert "Smartphone" in result
        assert "Baru" in result

    def test_price_deliberately_not_included(self):
        # Harga sudah tampil sebagai kartu di UI -- context string ke LLM
        # sengaja tidak mengulanginya (lihat komentar di utils/context.py).
        products = [{"name": "Produk X", "category": "Gadget", "condition": "Baru"}]
        result = build_product_context(products)
        assert "12,000,000" not in result
        assert "12000000" not in result

    def test_multiple_products_all_included(self):
        products = [
            {"name": f"Produk {i}", "category": "Gadget", "condition": "Baru"} for i in range(1, 4)
        ]
        result = build_product_context(products)
        assert "Produk 1" in result
        assert "Produk 2" in result
        assert "Produk 3" in result
