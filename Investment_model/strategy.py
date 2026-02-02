import pandas as pd
import numpy as np
from pathlib import Path

# ========= 1. 環境參數設定 =========
INDICATOR_DIR = Path(r"C:\Users\tingo\Desktop\專題\company_indicators")
OUTPUT_DIR = Path(r"C:\Users\tingo\Desktop\專題\long_stable_strategy_outputs")
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

TARGET_PERIODS = [
    (2022, 4), (2023, 1), (2023, 2), (2023, 3), (2023, 4),
    (2024, 1), (2024, 2), (2024, 3), (2024, 4)
]
PERIOD_COLS = [f"{y}Q{q}" for y, q in TARGET_PERIODS]
NUM_PERIODS = len(TARGET_PERIODS)


# ========= 2. 評分模組 (進化版：動能與轉機導向) =========
class StrategyModules:
    @staticmethod
    def get_rank_score_from_series(target_val, series_pool, higher_is_better=True):
        s = pd.Series(series_pool).dropna()
        if s.empty: return 0
        ranks = s.rank(method='min', ascending=higher_is_better)
        # 尋找最接近的值
        idx = (s - target_val).abs().idxmin()
        return round(ranks[idx], 3)

    @staticmethod
    def get_time_weighted_rank(ranks_list):
        """核心：時間加權。讓最新的季度對原始分數貢獻最大"""
        # 修正 ValueError: 使用 len() 判斷 array 是否為空
        if ranks_list is None or len(ranks_list) == 0:
            return 0
        n = len(ranks_list)
        # 權重由 1.0 線性增加到 2.0 (最新的季度權重最高)
        weights = np.linspace(1.0, 2.0, n)
        weighted_avg = np.average(ranks_list, weights=weights)
        return round(weighted_avg, 3)

    @staticmethod
    def calc_dynamic_cashflow_management(ocf_series, fcf_series, n_count):
        """現金流管理：動態扣分機制"""
        scores = []
        curr = float(NUM_PERIODS) * n_count
        deduction_unit = n_count * 0.5
        for ocf, fcf in zip(ocf_series, fcf_series):
            if ocf < 0: curr *= 0.8
            if ocf > 0 and fcf < 0: curr -= deduction_unit
            curr = max(curr, 0)
            scores.append(round(curr, 3))
        return scores

    @staticmethod
    def calc_fcf_growth_rates(fcf_series):
        rates = [None]
        for i in range(1, len(fcf_series)):
            prev, curr = fcf_series[i - 1], fcf_series[i]
            denom = abs(prev) if prev != 0 else 1.0
            rates.append(round((curr - prev) / denom, 4))
        return rates


# ========= 3. 主執行程序 =========
def main():
    files = list(INDICATOR_DIR.glob("*_Financial_Indicators.csv"))
    temp_valid_data = {}
    mod = StrategyModules()

    # A. 數據讀取與格式標準化
    for f in files:
        cid = str(f.name.split('_')[0])
        try:
            df = pd.read_csv(f)
            df['period_tuple'] = list(zip(df['年度'].astype(int), df['季度'].astype(int)))
            f_df = df[df['period_tuple'].isin(TARGET_PERIODS)].copy()
            if len(f_df) == NUM_PERIODS:
                f_df['p_str'] = f_df.apply(lambda r: f"{int(r['年度'])}Q{int(r['季度'])}", axis=1)
                f_df['p_str'] = pd.Categorical(f_df['p_str'], categories=PERIOD_COLS, ordered=True)
                temp_valid_data[cid] = f_df.sort_values('p_str')
        except Exception as e:
            print(f"跳過檔案 {f.name}，原因: {e}")

    if not temp_valid_data:
        return print(f"❌ 數據不足。")

    all_cids = sorted(temp_valid_data.keys())
    industry_map = {}
    industry_dict = {"1": "上游", "2": "中游", "3": "下游"}

    # B. 產業地位設定
    print(f"\n===== 發現 {len(all_cids)} 家合格公司 =====")
    for cid in all_cids:
        while True:
            pos = input(f"輸入公司 【{cid}】 的產業地位 (1:上游, 2:中游, 3:下游)：").strip()
            if pos in industry_dict:
                industry_map[cid] = industry_dict[pos]
                break
            print("⚠️ 輸入錯誤，請重新輸入。")

    valid_data = temp_valid_data
    industry_groups = {"上游": {}, "中游": {}, "下游": {}}
    for cid, df in valid_data.items():
        pos = industry_map.get(cid)
        if pos in industry_groups: industry_groups[pos][cid] = df

    # C. 計算產業指標池
    industry_pools = {}
    for pos, group_data in industry_groups.items():
        if not group_data: continue
        n_count = len(group_data)

        industry_pools[pos] = {
            'N': n_count,
            'growth_sum': pd.Series(
                {c: sum([r for r in mod.calc_fcf_growth_rates(d['FCF'].values) if r is not None]) for c, d in
                 group_data.items()}),
            'ccc_m': pd.Series({c: d['CCC'].mean() for c, d in group_data.items()}),
            'ccc_s': pd.Series({c: d['CCC'].std() for c, d in group_data.items()}),
            'dr_all': pd.DataFrame({c: d['負債比率(%)'].values for c, d in group_data.items()}),
            'dr_s': pd.Series({c: d['負債比率(%)'].std() for c, d in group_data.items()}),
            'de_all': pd.DataFrame({c: d['D/E Ratio'].values for c, d in group_data.items()}),
            'ltd_all': pd.DataFrame({c: d['長期負債比率(%)'].values for c, d in group_data.items()}),
            'icr_all': pd.DataFrame({c: d['ICR'].values for c, d in group_data.items()}),
            'cr_all': pd.DataFrame({c: d['流動比率(%)'].values for c, d in group_data.items()}),
            'qr_all': pd.DataFrame({c: d['速動比率(%)'].values for c, d in group_data.items()}),
            'ebitda_all': pd.DataFrame({c: d['EBITDA Margin'].values for c, d in group_data.items()}),
            'ebitda_sd_ranks': pd.Series({c: d['EBITDA Margin'].std() for c, d in group_data.items()}).rank(
                ascending=False),
            'gm_all': pd.DataFrame({c: d['毛利率(%)'].values for c, d in group_data.items()}),
            'gm_sd_ranks': pd.Series({c: d['毛利率(%)'].std() for c, d in group_data.items()}).rank(ascending=False),
            'pm_all': pd.DataFrame({c: d['利潤率(%)'].values for c, d in group_data.items()})
        }

    # D. 建立矩陣報表
    matrix_rows = []
    for cid in sorted(valid_data.keys()):
        pos = industry_map.get(cid)
        pool = industry_pools[pos]
        N, df = pool['N'], valid_data[cid]

        # 修正 KeyError: 加入 'D/E Ratio' 到 v 字典中
        cols_to_extract = ['OCF', 'FCF', 'CCC', '負債比率(%)', 'D/E Ratio', 'ICR', '毛利率(%)', 'EBITDA Margin',
                           '利潤率(%)', '流動比率(%)', '速動比率(%)']
        v = {col: df[col].values for col in cols_to_extract}

        matrix_rows.append(
            {'Type': 'COMP', '指標': f"【{cid}】 產業地位：{pos}", **{p: '' for p in PERIOD_COLS}, '統計/加權分': '---'})

        # --- 2. 現金流管理 ---
        cf_dynamic = mod.calc_dynamic_cashflow_management(v['OCF'], v['FCF'], N)
        quality_score = cf_dynamic[-1]
        g_score = (pool['growth_sum'].rank(ascending=True).get(cid, 0)) * NUM_PERIODS
        cf_total = round(quality_score * 0.6 + g_score * 0.4, 1)

        matrix_rows.append({'Type': 'DATA', '指標': 'OCF / FCF 數據',
                            **{PERIOD_COLS[i]: f"{int(v['OCF'][i])}/{int(v['FCF'][i])}" for i in range(NUM_PERIODS)},
                            '統計/加權分': ""})
        matrix_rows.append({'Type': 'SCR', '指標': ' └─ 營運品質(動態) / 成長動能得分', **{p: '' for p in PERIOD_COLS},
                            '統計/加權分': f"品質:{quality_score} 成長:{g_score}"})
        matrix_rows.append({'Type': 'TOT', '指標': '★ 現金流管理加權總評分', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': cf_total})

        # --- 3. 經營效率 CCC ---
        m_rank = pool['ccc_m'].rank(ascending=False).get(cid, 0)
        s_rank = pool['ccc_s'].rank(ascending=False).get(cid, 0)
        ccc_weighted = round((m_rank * 0.8 + s_rank * 0.2) * NUM_PERIODS, 1)
        matrix_rows.append({'Type': 'DATA', '指標': 'CCC 營運天數', **dict(zip(PERIOD_COLS, v['CCC'])),
                            '統計/加權分': f"平均:{round(v['CCC'].mean(), 1)}"})
        matrix_rows.append({'Type': 'TOT', '指標': '★ CCC 經營效率評分 (動能優化)', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': ccc_weighted})

        # --- 4. 負債比率 ---
        dr_ranks = pool['dr_all'].rank(axis=1, ascending=False).loc[:, cid].values
        dr_weighted_rank = mod.get_time_weighted_rank(dr_ranks)
        dr_s_score = (pool['dr_s'].rank(ascending=False).get(cid, 0))
        debt_total = round((dr_weighted_rank * 0.7 + dr_s_score * 0.3) * NUM_PERIODS, 1)
        matrix_rows.append({'Type': 'DATA', '指標': '負債比率 (%)', **dict(zip(PERIOD_COLS, v['負債比率(%)'])),
                            '統計/加權分': f"標差:{round(v['負債比率(%)'].std(), 2)}"})
        matrix_rows.append({'Type': 'TOT', '指標': '★ 財務結構評分 (時間加權版)', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': debt_total})

        # --- 5. D/E Ratio ---
        de_ranks = pool['de_all'].rank(axis=1, ascending=False).loc[:, cid].values
        de_avg = pool['de_all'].mean(axis=1).values
        de_final_scores = []
        for i in range(NUM_PERIODS):
            base = de_ranks[i]
            if v['D/E Ratio'][i] > de_avg[i] * 1.5:
                base = 1.0
            elif v['D/E Ratio'][i] < de_avg[i] * 0.5 and v['OCF'][i] < 0:
                base *= 0.7
            de_final_scores.append(base)
        matrix_rows.append({'Type': 'TOT', '指標': '★ D/E 財務結構最終評分', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': round(sum(de_final_scores), 1)})

        # --- 6. 長期負債結構 ---
        ltd_ranks = pool['ltd_all'].rank(axis=1, ascending=False).loc[:, cid].values
        icr_ranks = pool['icr_all'].rank(axis=1, ascending=True).loc[:, cid].values
        icr_raw = v['ICR']
        icr_final_ranks = [1.0 if (r < 1 or np.isnan(r)) else icr_ranks[i] for i, r in enumerate(icr_raw)]
        ltd_score = mod.get_time_weighted_rank(ltd_ranks)
        icr_score = mod.get_time_weighted_rank(icr_final_ranks)
        struct_total = round((icr_score * 0.6 + ltd_score * 0.4) * NUM_PERIODS, 1)
        matrix_rows.append({'Type': 'TOT', '指標': '★ 長期負債結構最終評分', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': struct_total})

        # --- 7. 短期償債能力 ---
        cr_ranks = pool['cr_all'].rank(axis=1, ascending=True).loc[:, cid].values
        qr_ranks = pool['qr_all'].rank(axis=1, ascending=True).loc[:, cid].values
        liq_scores = []
        for i in range(NUM_PERIODS):
            base = cr_ranks[i] * 0.6 + qr_ranks[i] * 0.4
            if v['流動比率(%)'][i] < 100:
                score = 1.0
            elif v['速動比率(%)'][i] < 100:
                score = max(base * 0.7, 1.0)
            else:
                score = base
            liq_scores.append(score)
        matrix_rows.append({'Type': 'TOT', '指標': '★ 短期償債能力最終評分', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': round(sum(liq_scores), 1)})

        # --- 8. EBITDA Margin ---
        eb_ranks = pool['ebitda_all'].rank(axis=1, ascending=True).loc[:, cid].values
        eb_weighted_rank = mod.get_time_weighted_rank(eb_ranks)
        eb_sd_score = pool['ebitda_sd_ranks'].get(cid, 0)
        eb_total = round((eb_weighted_rank * 0.7 + eb_sd_score * 0.3) * NUM_PERIODS, 1)
        matrix_rows.append({'Type': 'TOT', '指標': '★ 獲利能力(EBITDA)評分', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': eb_total})

        # --- 9. 毛利率 ---
        gm_ranks = pool['gm_all'].rank(axis=1, ascending=True).loc[:, cid].values
        gm_weighted_rank = mod.get_time_weighted_rank(gm_ranks)
        gm_sd_score = pool['gm_sd_ranks'].get(cid, 0)
        base_gm = (gm_weighted_rank * 0.6 + gm_sd_score * 0.4) * NUM_PERIODS
        gm_vals = v['毛利率(%)']
        cum_growth = (gm_vals[-1] - gm_vals[0]) / abs(gm_vals[0]) if gm_vals[0] != 0 else 0
        if cum_growth <= 0 and not all(x > 0 for x in gm_vals):
            final_gm = 1.0
        elif cum_growth < 0.06:
            final_gm = base_gm * 0.7
        else:
            final_gm = base_gm
        matrix_rows.append({'Type': 'TOT', '指標': '★ 毛利率最終評分', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': round(final_gm, 1)})

        # --- 10. 利潤率 ---
        pm_ranks = pool['pm_all'].rank(axis=1, ascending=True).loc[:, cid].values
        pm_final_scores = [1.0 if v['利潤率(%)'][i] < 0 else pm_ranks[i] for i in range(NUM_PERIODS)]
        pm_total = round(mod.get_time_weighted_rank(pm_final_scores) * NUM_PERIODS, 1)
        matrix_rows.append(
            {'Type': 'TOT', '指標': '★ 利潤率最終評分', **{p: '---' for p in PERIOD_COLS}, '統計/加權分': pm_total})

        matrix_rows.append({k: '' for k in ['Type', '指標', *PERIOD_COLS, '統計/加權分']})

    # E. Excel 輸出
    report_df = pd.DataFrame(matrix_rows)
    excel_path = OUTPUT_DIR / "turnaround_momentum_matrix_v2.xlsx"
    writer = pd.ExcelWriter(excel_path, engine='xlsxwriter')
    report_df.to_excel(writer, index=False, sheet_name='財務評分矩陣')
    workbook, worksheet = writer.book, writer.sheets['財務評分矩陣']

    fmt_comp = workbook.add_format({'bold': True, 'font_color': '#FFFFFF', 'bg_color': '#4472C4', 'border': 1})
    fmt_data = workbook.add_format({'border': 1, 'font_size': 9})
    fmt_tot = workbook.add_format({'bold': True, 'bg_color': '#FFD966', 'border': 2, 'align': 'center'})

    for i, row in enumerate(matrix_rows):
        idx = i + 1
        t = row.get('Type')
        if t == 'COMP':
            worksheet.set_row(idx, None, fmt_comp)
        elif t in ['DATA', 'SCR']:
            worksheet.set_row(idx, None, fmt_data)
        elif t == 'TOT':
            worksheet.set_row(idx, None, fmt_tot)

    worksheet.set_column('A:A', 0)
    worksheet.set_column('B:B', 45)
    worksheet.set_column(2, 2 + NUM_PERIODS - 1, 12)
    worksheet.set_column(2 + NUM_PERIODS, 2 + NUM_PERIODS, 30)
    writer.close()
    print(f"🏆 轉機動能矩陣已產出：{excel_path.name}")


if __name__ == "__main__":
    main()
