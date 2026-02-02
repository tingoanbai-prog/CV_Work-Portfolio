import pandas as pd
import re
import numpy as np
from pathlib import Path
import warnings

# 隱藏 pandas 警告
warnings.simplefilter("ignore", category=UserWarning)

# ========= 1. 環境參數設定 =========
SOURCE_DIR = Path(r"C:\Users\tingo\Desktop\專題\mops_outputs")
OUTPUT_DIR = Path(r"C:\Users\tingo\Desktop\專題\cleaned_data")
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

# ========= 2. 定義匹配規則 (包含您提供的 CF 特定名稱) =========
MAPPINGS = {
    'BS': {
        "name": "資產負債表",
        "fields": {
            "資產總額": r"資產總(額|計)$",
            "負債總額": r"負債總(額|計)$",
            "權益總額": r"(權益|歸屬於母公司業主之權益)總(額|計)$",
            "應收帳款": r"應收帳款(淨額)?$",
            "存貨": r"^存貨$",
            "應付帳款": r"^應付帳款$",
            "流動資產": r"流動資產(合計|總計)$",
            "流動負債": r"流動負債(合計|總計)$",
            "非流動負債": r"非流動負債(合計|總計)$"
        }
    },
    'IS': {
        "name": "綜合損益表",
        "fields": {
            "營業收入": r"營業收入(合計)?$",
            "營業成本": r"營業成本(合計)?$",
            # 強化：匹配稅前淨利/淨損/利益/純益，且已處理清理後的字串
            "稅前淨利": r"稅前(淨利|淨損|利益|純益|純損)",
            "本期淨利": r"本期(淨利|淨損|利益|純益|純損)"
        }
    },
    'CF': {
        "name": "現金流量表",
        "fields": {
            # 針對您提供的名稱，匹配清理後的字串 (移除空格與括號後)
            "營業現金流": r"營業活動之淨現金流入流出",
            "投資現金流": r"投資活動之淨現金流入流出",
            "利息費用": r"利息費用",
            "折舊費用": r"折舊費用",
            "攤銷費用": r"攤銷費用"
        }
    }
}


# ========= 3. 工具函式 =========

def clean_numeric(val):
    """精準處理會計格式負數 (1,234.5) -> -1234.5"""
    if pd.isna(val) or str(val).strip() == "":
        return 0.0
    s = str(val).replace(',', '').strip()
    # 判斷是否為會計括號負數
    if re.match(r'^[\(（].+[\)）]$', s):
        s = "-" + re.sub(r'[()（）]', '', s)
    try:
        return float(s)
    except ValueError:
        return 0.0


def parse_metadata(filename):
    """從檔名提取資訊"""
    pattern = r"(\d+)_(\d+)Q(\d+)_(.+)\.xlsx"
    match = re.search(pattern, filename)
    if match:
        return match.group(1), int(match.group(2)), int(match.group(3)), match.group(4)
    return None, None, None, None


def extract_data_from_file(file_path, mapping_fields):
    """讀取 Excel 並抓取數據"""
    try:
        df = pd.read_excel(file_path, header=None, engine='openpyxl')
        cid, year, season, _ = parse_metadata(file_path.name)
        row_data = {"公司代號": cid, "年度": year, "季度": season, **{k: 0.0 for k in mapping_fields.keys()}}
        found_keys = set()

        for _, row in df.iterrows():
            # 【關鍵】清理科目名稱：移除所有空格、括號、底線等
            raw_item_name = str(row.iloc[0])
            item_name = re.sub(r'[\s　\-\t\._\(\)（）]+', '', raw_item_name)

            if item_name == 'nan' or not item_name: continue

            for target_key, pattern in mapping_fields.items():
                if target_key not in found_keys and re.search(pattern, item_name):
                    # 搜尋該列後面第一個出現的數值
                    for val in row.iloc[1:]:
                        if pd.notna(val) and str(val).strip() != "":
                            row_data[target_key] = clean_numeric(val)
                            found_keys.add(target_key)
                            break
        return row_data
    except Exception as e:
        print(f"❌ 讀取出錯 {file_path.name}: {e}")
        return None


def get_user_selection():
    """獲取掃描與篩選結果"""
    files = [f for f in SOURCE_DIR.glob("*.xlsx") if not f.name.startswith("~$")]
    all_info = []
    for f in files:
        cid, year, q, r_type = parse_metadata(f.name)
        if cid: all_info.append({"cid": cid, "year": year, "q": q, "type": r_type, "path": f})

    df_files = pd.DataFrame(all_info)
    if df_files.empty: return None

    print("\n" + "=" * 50)
    print("📋 已掃描到的報表摘要：")
    print(df_files.groupby(['cid', 'year', 'type']).size().unstack(fill_value='-'))
    print("=" * 50)

    sel_cid = input("\n請輸入公司代號 (多個用逗號, Enter 全選): ").strip().split(',')
    if sel_cid == ['']: sel_cid = df_files['cid'].unique()

    sel_year = input("請輸入年份 (Enter 全選): ").strip().split(',')
    if sel_year == ['']:
        sel_year = df_files['year'].unique()
    else:
        sel_year = [int(y) for y in sel_year]

    mask = df_files['cid'].isin(sel_cid) & df_files['year'].isin(sel_year)
    return df_files[mask]


# ========= 4. 核心執行流程 =========

def run_cleaning(selected_df):
    for r_code, info in MAPPINGS.items():
        type_name = info['name']
        mapping_fields = info['fields']
        current_group = selected_df[selected_df['type'] == type_name]

        if current_group.empty: continue

        print(f"\n🚀 正在清洗：{type_name}...")
        results = []
        for _, row in current_group.iterrows():
            data = extract_data_from_file(row['path'], mapping_fields)
            if data: results.append(data)

        if not results: continue
        df_res = pd.DataFrame(results).sort_values(by=["公司代號", "年度", "季度"]).reset_index(drop=True)

        # 【單季還原】僅針對損益表與現金流量表
        if r_code in ['IS', 'CF']:
            print(f"🔄 執行累計值轉單季值 (並處理負數相減)...")
            df_single = df_res.copy()
            indices_to_drop = []

            for idx, row in df_res.iterrows():
                if row['季度'] == 1: continue

                prev_q = row['季度'] - 1
                prev_data = df_res[(df_res['公司代號'] == row['公司代號']) &
                                   (df_res['年度'] == row['年度']) &
                                   (df_res['季度'] == prev_q)]

                if not prev_data.empty:
                    # 數學還原：Q2累計 - Q1累計 (正確處理負數，如 -200 - (-150) = -50)
                    for k in mapping_fields.keys():
                        df_single.at[idx, k] = float(row[k]) - float(prev_data.iloc[0][k])
                else:
                    print(f"⚠️ 找不到基底: {row['公司代號']} {row['年度']}Q{row['季度']} 缺乏 Q{prev_q}。")
                    indices_to_drop.append(idx)

            if indices_to_drop:
                confirm = input(f"❓ 有 {len(indices_to_drop)} 筆資料無法還原。是否刪除？ (Y/N): ").strip().upper()
                if confirm == 'Y':
                    df_single = df_single.drop(indices_to_drop).reset_index(drop=True)
                    print("🗑️ 已剔除缺失資料。")
            df_res = df_single

        # 輸出結果
        out_path = OUTPUT_DIR / f"{r_code}_Cleaned_Data.csv"
        df_res.to_csv(out_path, index=False, encoding="utf-8-sig")
        print(f"✨ {type_name} 清洗完成 -> {out_path.name}")


def main():
    print("=== MOPS 數據清洗與單季還原工具 (全功能版) ===")
    selected = get_user_selection()
    if selected is not None and not selected.empty:
        run_cleaning(selected)
        print(f"\n✅ 全部任務已完成！\n存放路徑: {OUTPUT_DIR}")
    else:
        print("👋 未選擇任何檔案，程式結束。")


if __name__ == "__main__":
    main()