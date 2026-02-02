import pandas as pd
import numpy as np
from pathlib import Path

# ========= 1. 環境參數設定 =========
SOURCE_DIR = Path(r"C:\Users\tingo\Desktop\專題\cleaned_data")
OUTPUT_DIR = Path(r"C:\Users\tingo\Desktop\專題\company_indicators")
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)


def load_and_merge_data():
    """載入並合併資產負債表(BS)、損益表(IS)、現金流量表(CF)"""
    try:
        df_bs = pd.read_csv(SOURCE_DIR / "BS_Cleaned_Data.csv")
        df_is = pd.read_csv(SOURCE_DIR / "IS_Cleaned_Data.csv")
        df_cf = pd.read_csv(SOURCE_DIR / "CF_Cleaned_Data.csv")

        df_merged = pd.merge(df_bs, df_is, on=["公司代號", "年度", "季度"], how="outer")
        df_merged = pd.merge(df_merged, df_cf, on=["公司代號", "年度", "季度"], how="outer")

        return df_merged.sort_values(by=["公司代號", "年度", "季度"]).reset_index(drop=True)
    except FileNotFoundError as e:
        print(f"❌ 錯誤: 找不到 CSV 檔案，請確認清洗程式已執行。\n{e}")
        return None


def calculate_indicators(df):
    """根據清洗後的單季資料計算財務指標"""
    d = df.copy()

    # --- 預處理：避免除以零 ---
    denominator_cols = ['營業收入', '營業成本', '資產總額', '權益總額', '流動負債', '利息費用']
    for col in denominator_cols:
        if col in d.columns:
            d[col] = d[col].replace(0, np.nan)

    # --- A. 經營能力 (Time Factor: 90 days) ---
    d['DSO'] = (d['應收帳款'] / d['營業收入']) * 90
    d['DIO'] = (d['存貨'] / d['營業成本']) * 90
    d['DPO'] = (d['應付帳款'] / d['營業成本']) * 90
    d['CCC'] = d['DSO'] + d['DIO'] - d['DPO']
    d['OCF'] = d['營業現金流']
    d['FCF'] = d['營業現金流'] + d['投資現金流']

    # --- B. 財務結構 ---
    d['負債比率(%)'] = (d['負債總額'] / d['資產總額']) * 100
    if '非流動負債' in d.columns:
        d['長期負債比率(%)'] = (d['非流動負債'] / d['資產總額']) * 100
    else:
        d['長期負債比率(%)'] = 0  # 若無資料則補 0
    # 【修正點】D/E Ratio：移除 * 100，改為純數值比率
    d['D/E Ratio'] = d['負債總額'] / d['權益總額']

    d['ICR'] = (d['稅前淨利'] + d['利息費用'].fillna(0)) / d['利息費用']
    d['流動比率(%)'] = (d['流動資產'] / d['流動負債']) * 100
    d['速動比率(%)'] = ((d['流動資產']-d['存貨'])/ d['流動負債']) * 100

    # --- C. 獲利能力 (單季) ---
    d['EBITDA'] = d['稅前淨利'] + d['利息費用'].fillna(0) + d['折舊費用'].fillna(0) + d['攤銷費用'].fillna(0)
    d['EBITDA Margin'] = d['EBITDA'] / d['營業收入']
    d['毛利率(%)'] = ((d['營業收入'] - d['營業成本']) / d['營業收入']) * 100
    d['利潤率(%)'] = (d['本期淨利'] / d['營業收入']) * 100

    return d


def save_by_company(df):
    """將結果根據公司代號拆分儲存"""
    company_list = df['公司代號'].unique()

    print(f"\n📂 開始產出各公司財務指標分析...")
    for cid in company_list:
        company_df = df[df['公司代號'] == cid].copy()

        # 欄位排序 (同步更新 D/E Ratio 名稱)
        target_cols = [
            '公司代號', '年度', '季度',
            'DSO', 'DIO', 'DPO', 'CCC','OCF', 'FCF',
            '負債比率(%)', 'D/E Ratio', '長期負債比率(%)','ICR', '流動比率(%)','速動比率(%)',
            'EBITDA', 'EBITDA Margin', '毛利率(%)', '利潤率(%)'
        ]

        final_cols = [c for c in target_cols if c in company_df.columns]
        company_df = company_df[final_cols]

        numeric_cols = company_df.select_dtypes(include=[np.number]).columns
        company_df[numeric_cols] = company_df[numeric_cols].round(2)

        out_path = OUTPUT_DIR / f"{cid}_Financial_Indicators.csv"
        company_df.to_csv(out_path, index=False, encoding="utf-8-sig")
        print(f"✅ 計算完成: {cid} -> {out_path.name}")


def main():
    print("=== 財務指標校正計算工具 (D/E Ratio 數值化版) ===")
    raw_data = load_and_merge_data()
    if raw_data is None: return

    indicator_df = calculate_indicators(raw_data)
    save_by_company(indicator_df)

    print(f"\n✨ 全部計算任務已完成！")
    print(f"📍 產出目錄：{OUTPUT_DIR}")


if __name__ == "__main__":
    main()