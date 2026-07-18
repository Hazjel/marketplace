import pytest
from main import _parse_classifier_output, _build_product_context


# ---------------------------------------------------------------------------
# _parse_classifier_output
# ---------------------------------------------------------------------------
class TestParseClassifierOutput:
    def test_strict_format_ya(self):
        found, kw = _parse_classifier_output("YA|laptop gaming")
        assert found is True
        assert kw == "laptop gaming"

    def test_strict_format_tidak(self):
        found, kw = _parse_classifier_output("TIDAK|none")
        assert found is False
        assert kw == "none"

    def test_colon_separator(self):
        found, kw = _parse_classifier_output("YA: airpods")
        assert found is True
        assert kw == "airpods"

    def test_dash_separator(self):
        found, kw = _parse_classifier_output("YA - mechanical keyboard")
        assert found is True
        assert kw == "mechanical keyboard"

    def test_empty_string(self):
        found, kw = _parse_classifier_output("")
        assert found is False
        assert kw == "none"

    def test_keyword_none_treated_as_not_found(self):
        found, kw = _parse_classifier_output("YA|none")
        assert found is False

    def test_multiline_only_first_line_used(self):
        found, kw = _parse_classifier_output("YA|laptop\nbeberapa teks lain")
        assert found is True
        assert kw == "laptop"

    def test_lowercase_ya(self):
        found, kw = _parse_classifier_output("ya|smartphone")
        assert found is True
        assert kw == "smartphone"


# ---------------------------------------------------------------------------
# _build_product_context
# ---------------------------------------------------------------------------
class TestBuildProductContext:
    def test_empty_products_returns_not_found_message(self):
        result = _build_product_context([])
        assert "tidak ditemukan" in result.lower()

    def test_products_list_included_in_context(self):
        products = [
            {
                "name": "ROG Phone 8",
                "price": 12000000,
                "store": "TechStore",
                "category": "Smartphone",
                "condition": "Baru",
                "stock": 5,
                "total_sold": 100,
            }
        ]
        result = _build_product_context(products)
        assert "ROG Phone 8" in result
        assert "TechStore" in result
        assert "12,000,000" in result

    def test_multiple_products_all_included(self):
        products = [
            {
                "name": f"Produk {i}",
                "price": 1000 * i,
                "store": "Store",
                "category": "Gadget",
                "condition": "Baru",
                "stock": i,
                "total_sold": i * 10,
            }
            for i in range(1, 4)
        ]
        result = _build_product_context(products)
        assert "Produk 1" in result
        assert "Produk 2" in result
        assert "Produk 3" in result
