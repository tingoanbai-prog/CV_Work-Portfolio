import time
import random
from pathlib import Path
import io
import re
import httpx
import pandas as pd
import truststore

# ========= 輸出資料夾 =========
OUT_DIR = Path(r"C:\Users\tingo\Desktop\專題\mops_outputs")
OUT_DIR.mkdir(parents=True, exist_ok=True)

# ========= 常數 =========
BASE = "https://mopsfin.twse.com.tw"
INDEX_URL = f"{BASE}/index"
REPORT_URL = f"{BASE}/compare/report"
EXPORT_URL = f"{BASE}/export/report"
HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0 Safari/537.36"
    ),
    "Referer": INDEX_URL,
    "X-Requested-With": "XMLHttpRequest",
}
truststore.inject_into_ssl()


# ========= 小工具 =========
def _export_form(company_id: str, item: str, year: str, season: str) -> dict:
    return {
        "compareItem": item, "companyId": company_id, "ys": f"{year}{season}",
        "qnumber": season, "quarter": season, "bcodeAvg": "Y", "companyAvg": "Y",
        "ylabel": "", "selectYear": year, "selectSeason": season,
        "year": year, "season": season, "yearseason": f"{year}{season}",
    }


def download_official_excel(client, out_path, company_id, year, season, item) -> bool:
    try:
        client.cookies.set("companyId", company_id, domain="mopsfin.twse.com.tw", path="/")
        form = _export_form(company_id, item, year, season)
        r = client.post(EXPORT_URL, data=form, timeout=60.0)
        ctype = (r.headers.get("Content-Type") or "").lower()
        r.raise_for_status()
        if not any(k in ctype for k in ("spreadsheetml", "octet-stream", "excel")):
            return False
        out_path.write_bytes(r.content)
        return True
    except:
        return False


def html_fallback_to_excel(client, out_path, company_id, year, season, item) -> bool:
    try:
        client.cookies.set("companyId", company_id, domain="mopsfin.twse.com.tw", path="/")
        form = _export_form(company_id, item, year, season)
        r = client.post(REPORT_URL, data=form, timeout=30.0)
        r.raise_for_status()
        dfs = pd.read_html(io.StringIO(r.text), flavor="lxml")
        if not dfs: return False
        with pd.ExcelWriter(out_path, engine="xlsxwriter") as w:
            for i, df in enumerate(dfs, start=1):
                if isinstance(df.columns, pd.MultiIndex):
                    df.columns = ["_".join(str(x) for x in tup if pd.notna(x)).strip("_") for tup in df.columns]
                df.to_excel(w, index=False, sheet_name=f"Table_{i}")
        return True
    except:
        return False


# ========= 主要流程 =========
def main():
    print("=== MOPS 財報下載工具 (基底自動補齊版) ===")

    # 1. 公司代號
    raw_ids = input("請輸入公司代號 (用逗號或空格分隔)：")
    company_ids = [cid.strip() for cid in re.split(r'[,\s]+', raw_ids) if cid.strip()]

    # 2. 報表種類
    item_map = {"1": {"code": "BalanceSheet", "name": "資產負債表"},
                "2": {"code": "IncomeStatement", "name": "綜合損益表"},
                "3": {"code": "CashflowStatement", "name": "現金流量表"}}
    print("\n[報表選擇] (可複選，如 1,2,3)")
    for k, v in item_map.items(): print(f"  {k}) {v['name']}")
    raw_choices = input("  請選擇: ").strip()
    selected_targets = [item_map[k] for k in re.split(r'[,\s]+', raw_choices) if k in item_map]

    # 3. 區間設定
    print("\n[區間設定] YYYYQQ 格式 (例如 202203)")
    start_str = input("  起始區間: ").strip()
    end_str = input("  結束區間: ").strip()

    start_y, start_q = int(start_str[:4]), int(start_str[4:])
    end_y, end_q = int(end_str[:4]), int(end_str[4:])

    # --- 關鍵修正：任務導向的下載邏輯 ---
    tasks = []
    for cid in company_ids:
        for y in range(start_y, end_y + 1):
            for q in range(1, 5):
                if y == start_y and q < start_q: continue
                if y == end_y and q > end_q: continue

                # A. 正常下載使用者要求的報表
                for target in selected_targets:
                    tasks.append((cid, str(y), str(q), target))

                # B. 自動補齊基底：僅限 IS 和 CF 在起始點非 Q1 時
                if y == start_y and q == start_q and q > 1:
                    base_q = q - 1
                    for target in selected_targets:
                        if target['code'] in ['IncomeStatement', 'CashflowStatement']:
                            # 檢查任務是否已存在，避免重複
                            tasks.insert(0, (cid, str(y), str(base_q), target))
                            print(f"💡 針對 {cid} 自動加入 {y}Q{base_q} 的 {target['name']} 作為還原基底。")

    print(f"\n🚀 預計處理 {len(tasks)} 個下載任務...")

    with httpx.Client(headers=HEADERS, timeout=30.0, verify=True) as client:
        client.get(INDEX_URL)
        for i, (cid, y, q, target) in enumerate(tasks, 1):
            filename = f"{cid}_{y}Q{q}_{target['name']}.xlsx"
            out_path = OUT_DIR / filename
            print(f"[{i}/{len(tasks)}] {cid} {y}Q{q} {target['name']}...", end=" ", flush=True)

            if out_path.exists():
                print("⏭️ 跳過")
                continue

            success = download_official_excel(client, out_path, cid, y, q, target['code'])
            if not success:
                success = html_fallback_to_excel(client, out_path, cid, y, q, target['code'])

            print("✅ 成功" if success else "❌ 失敗")
            time.sleep(random.uniform(0.8, 1.5))

    print(f"\n✨ 下載完成！")


if __name__ == "__main__":
    main()
