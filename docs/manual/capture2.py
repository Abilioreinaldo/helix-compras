"""
Capturas adicionais (telas com dados + modais) para o manual.
Requer o seed (seed_manual.php) executado e o app em http://localhost:8125.
"""
import pathlib
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8125"
ASSETS = pathlib.Path(__file__).parent / "assets"
PWD = "senha@123"
VP = {"width": 1440, "height": 900}


def login(page, email):
    page.goto(f"{BASE}/login", wait_until="networkidle")
    page.fill("#email", email)
    page.fill("#senha", PWD)
    page.click("button[type=submit]")
    try:
        page.wait_for_url("**/dashboard", timeout=8000)
    except Exception:
        page.wait_for_timeout(1500)


def save(page, name, full=True):
    page.wait_for_timeout(900)
    page.screenshot(path=str(ASSETS / f"{name}.png"), full_page=full)
    print("  OK ", name)


with sync_playwright() as p:
    br = p.chromium.launch()

    # 1) Triagem populada (com badge Via Expressa) — compradora
    ctx = br.new_context(viewport=VP); pg = ctx.new_page()
    login(pg, "compradora@comendador.com.br")
    pg.goto(f"{BASE}/compradora/triagem", wait_until="networkidle")
    save(pg, "12-compradora-triagem")  # sobrescreve a versão vazia
    ctx.close()

    # 2) Modal de Preços Homologados — admin
    ctx = br.new_context(viewport=VP); pg = ctx.new_page()
    login(pg, "admin@comendador.com.br")
    pg.goto(f"{BASE}/admin/catalogo-itens", wait_until="networkidle")
    pg.wait_for_timeout(800)
    try:
        pg.locator("button:has-text('Preços')").first.click()
        pg.wait_for_selector("text=Preços Homologados", timeout=5000)
        save(pg, "27-modal-homologacao", full=False)
    except Exception as e:
        print("  ERRO modal homologacao:", e)
    ctx.close()

    # 3) Painel de aprovação + modal de decisão por linha — gestor
    ctx = br.new_context(viewport=VP); pg = ctx.new_page()
    login(pg, "gestor@comendador.com.br")
    pg.goto(f"{BASE}/aprovacoes", wait_until="networkidle")
    pg.wait_for_timeout(600)
    try:
        pg.locator("a:has-text('Revisar'), button:has-text('Revisar')").first.click()
        pg.wait_for_timeout(1500)
        save(pg, "17-painel-aprovacao")
        # abre o modal Aprovar (decisão por linha)
        pg.locator("button:has-text('Aprovar')").first.click()
        pg.wait_for_timeout(800)
        save(pg, "18-modal-decisao-linha", full=False)
    except Exception as e:
        print("  ERRO painel aprovacao:", e)
    ctx.close()

    br.close()
print("Capturas adicionais concluídas.")
