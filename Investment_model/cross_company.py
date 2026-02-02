import pandas as pd
import re
from pathlib import Path

# ========= 1. 環境參數設定 =========
BASE_DIR = Path(r"C:\Users\tingo\Desktop\專題\long_stable_strategy_outputs")
INPUT_FILE = BASE_DIR / "modular_strategy_matrix_final.xlsx"
OUTPUT_FILE = BASE_DIR / "cross_company_comparison_final.xlsx"

# 定義指標分類對照表
INDICATOR_GROUPS = {
    "現金流管理加權總評分": "經營能力",
    "CCC評分": "經營能力",
    "財務結構評分": "財務結構",
    "D/E每季評分與總分": "財務結構",
    "長期負債結構最終評分": "財務結構",
    "短期償債能力最終評分": "財務結構",
    "獲利能力最終評分": "獲利能力",
    "毛利率最終評分": "獲利能力",
    "利潤率最終評分": "獲利能力"
}


def clean_indicator_name(name):
    """清理指標名稱，精確匹配分類表"""
    name = re.sub(r'[★└─]', '', name)
    name = re.sub(r'\(.*?\)', '', name)
    name = re.sub(r'-\d+季制', '', name)
    return name.strip()


def extract_score(score_str):
    if pd.isna(score_str) or score_str == '---':
        return 0.0
    match = re.search(r"[-+]?\d*\.\d+|\d+", str(score_str))
    return float(match.group()) if match else 0.0


def main():
    if not INPUT_FILE.exists():
        print(f"❌ 找不到來源檔案: {INPUT_FILE}")
        return

    print(f"🔍 正在解析矩陣數據...")
    df_raw = pd.read_excel(INPUT_FILE)

    raw_comp_list = []
    current_comp, current_industry = None, None
    comp_scores_raw = {}

    # ========= 2. 第一階段：解析原始數據 (確保捕捉最後一間) =========
    for _, row in df_raw.iterrows():
        row_type = str(row['Type']).strip()
        indicator_raw = str(row['指標'])

        if row_type == 'COMP':
            # 遇到新公司，先存舊的
            if current_comp:
                raw_comp_list.append({'ID': current_comp, 'Industry': current_industry, 'Scores': comp_scores_raw})

            comp_match = re.search(r"【(.*?)】", indicator_raw)
            ind_match = re.search(r"產業地位：(.*)", indicator_raw)
            current_comp = comp_match.group(1) if comp_match else "未知"
            current_industry = ind_match.group(1) if ind_match else "未知"
            comp_scores_raw = {}  # 重置分數字典

        elif row_type == 'TOT' and current_comp:
            clean_name = clean_indicator_name(indicator_raw)
            score_val = extract_score(row['統計/加權分'])
            comp_scores_raw[clean_name] = score_val

    # 確保捕捉最後一間公司
    if current_comp:
        raw_comp_list.append({'ID': current_comp, 'Industry': current_industry, 'Scores': comp_scores_raw})

    # ========= 3. 第二階段：計算產業 N 並產生雙列數據 =========
    industry_counts = pd.Series([c['Industry'] for c in raw_comp_list]).value_counts()
    processed_rows = []
    target_groups = ["經營能力", "財務結構", "獲利能力"]

    for comp in raw_comp_list:
        cid, ind = comp['ID'], comp['Industry']
        n_count = industry_counts.get(ind, 1)

        # 準備 原始列
        row_raw = {('基本資訊', '公司代號'): cid, ('基本資訊', '產業地位'): ind,
                   ('基本資訊', '數據類型'): '原始評分', ('_internal_', 'priority'): 0}
        # 準備 調整列 (Score/N)
        row_adj = {('基本資訊', '公司代號'): cid, ('基本資訊', '產業地位'): ind,
                   ('基本資訊', '數據類型'): f'調整評分(/{n_count})', ('_internal_', 'priority'): 1}

        total_sum = 0
        for name, val in comp['Scores'].items():
            group = INDICATOR_GROUPS.get(name, "其他指標")
            row_raw[(group, name)] = val
            row_adj[(group, name)] = round(val / n_count, 3)
            if group in target_groups:
                total_sum += val

        row_raw[('綜合評點', '總分合計')] = total_sum
        row_adj[('綜合評點', '總分合計')] = round(total_sum / n_count, 3)

        processed_rows.append(row_raw)
        processed_rows.append(row_adj)

    # ========= 4. 建立 DataFrame 與 排序 (修正 MultiIndex 問題) =========
    comparison_df = pd.DataFrame(processed_rows)

    # 強制修正 MultiIndex 結構，避免 drop 時報錯
    if not isinstance(comparison_df.columns, pd.MultiIndex):
        comparison_df.columns = pd.MultiIndex.from_tuples(comparison_df.columns)

    # 排序邏輯
    industry_order = {'上游': 1, '中游': 2, '下游': 3}
    comparison_df[('_internal_', 'ind_idx')] = comparison_df[('基本資訊', '產業地位')].map(industry_order).fillna(99)

    comparison_df = comparison_df.sort_values(
        [('_internal_', 'ind_idx'), ('基本資訊', '公司代號'), ('_internal_', 'priority')])
    comparison_df = comparison_df.drop(columns='_internal_', level=0)

    # ========= 5. 輸出 Excel =========
    with pd.ExcelWriter(OUTPUT_FILE, engine='xlsxwriter') as writer:
        comparison_df.to_excel(writer, sheet_name='跨公司對比分析', index=False)

        workbook = writer.book
        worksheet = writer.sheets['跨公司對比分析']

        # 格式定義
        header_fmt = workbook.add_format(
            {'bold': True, 'bg_color': '#D7E4BC', 'border': 1, 'align': 'center', 'valign': 'vcenter'})
        sub_header_fmt = workbook.add_format({'bg_color': '#EBF1DE', 'border': 1, 'align': 'center'})
        adj_row_fmt = workbook.add_format({'bg_color': '#F2F2F2', 'italic': True, 'border': 1, 'align': 'center'})
        total_fmt = workbook.add_format({'bold': True, 'bg_color': '#FFC000', 'border': 1, 'align': 'center'})

        # 手動美化表頭
        cols = comparison_df.columns
        for col_num, (lvl1, lvl2) in enumerate(cols):
            worksheet.write(0, col_num, lvl1, header_fmt)
            worksheet.write(1, col_num, lvl2, sub_header_fmt)

        # 隔行著色 (調整評分行變灰色)
        for i in range(len(comparison_df)):
            row_type = comparison_df.iloc[i][('基本資訊', '數據類型')]
            if '調整' in str(row_type):
                worksheet.set_row(i + 2, 20, adj_row_fmt)

        worksheet.set_column(0, 2, 15)
        worksheet.set_column(3, len(cols) - 2, 20)
        worksheet.set_column(len(cols) - 1, len(cols) - 1, 15, total_fmt)
        worksheet.freeze_panes(2, 3)

    print(f"🏆 跨公司分類對比表（含原始與調整分數）已產出：{OUTPUT_FILE.name}")


if __name__ == "__main__":

    main()
