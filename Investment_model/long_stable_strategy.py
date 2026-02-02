import pandas as pd
import numpy as np
from pathlib import Path

# ========= 1. 環境參數設定 (動態季度策略區間) =========
INDICATOR_DIR = Path(r"C:\Users\tingo\Desktop\專題\company_indicators")
OUTPUT_DIR = Path(r"C:\Users\tingo\Desktop\專題\long_stable_strategy_outputs")
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

TARGET_PERIODS = [
    (2022, 4), (2023, 1), (2023, 2), (2023, 3), (2023, 4),
    (2024, 1), (2024, 2), (2024, 3), (2024, 4)
]
PERIOD_COLS = [f"{y}Q{q}" for y, q in TARGET_PERIODS]
NUM_PERIODS = len(TARGET_PERIODS)


# ========= 2. 評分模組 (核心邏輯封裝) =========
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
    def calc_dynamic_cashflow_management(ocf_series, fcf_series, n_count):
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
    def calc_growth_rates(series):
        """
        修正後的對稱成長率：解決負值轉正值的計算瑕疵
        公式：(今-昨) / ((|今|+|昨|)/2)
        """
        rates = [None]
        for i in range(1, len(series)):
            prev, curr = series[i - 1], series[i]
            denom = (abs(curr) + abs(prev)) / 2
            if denom == 0:
                rates.append(0.0)
            else:
                rates.append(round((curr - prev) / denom, 4))
        return rates

    @staticmethod
    def calc_ccc_score(mean_val, std_val, pool_dict):
        m_s = StrategyModules.get_rank_score_from_series(mean_val, pool_dict['ccc_m'], False)
        s_s = StrategyModules.get_rank_score_from_series(std_val, pool_dict['ccc_s'], False)
        weighted_val = (m_s * 0.15 + s_s * 0.85) * NUM_PERIODS
        return round(weighted_val, 3)


# ========= 3. 主執行程序 =========
def main():
    files = list(INDICATOR_DIR.glob("*_Financial_Indicators.csv"))
    temp_valid_data = {}
    mod = StrategyModules()

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
        return print(f"❌ 數據不足：沒有任何公司符合 {NUM_PERIODS} 季的資料要求。")

    all_cids = sorted(temp_valid_data.keys())
    industry_map = {}
    industry_dict = {"1": "上游", "2": "中游", "3": "下游"}

    print(f"\n===== 發現 {len(all_cids)} 家合格公司，請設定產業地位 =====")
    for cid in all_cids:
        while True:
            pos = input(f"請輸入公司 【{cid}】 的產業地位 (1:上游, 2:中游, 3:下游)：").strip()
            if pos in industry_dict:
                industry_map[cid] = industry_dict[pos]
                break
            print("⚠️ 輸入錯誤")

    industry_groups = {"上游": {}, "中游": {}, "下游": {}}
    for cid, df in temp_valid_data.items():
        pos = industry_map.get(cid)
        if pos in industry_groups: industry_groups[pos][cid] = df

    industry_pools = {}
    for pos, group_data in industry_groups.items():
        if not group_data: continue
        n_count = len(group_data)

        ocf_g_sum_map = {c: sum([r for r in mod.calc_growth_rates(d['OCF'].values) if r is not None]) for c, d in
                         group_data.items()}
        ocf_g_series = pd.Series(ocf_g_sum_map)

        dr_all = pd.DataFrame({c: d['負債比率(%)'].values for c, d in group_data.items()})
        dr_quarterly_ranks = dr_all.rank(axis=1, ascending=False, method='min')
        dr_growth_sum_map = {c: sum([r for r in mod.calc_growth_rates(d['負債比率(%)'].values) if r is not None]) for
                             c, d in group_data.items()}
        dr_growth_sum_series = pd.Series(dr_growth_sum_map)
        dr_g_sum_rank = dr_growth_sum_series.rank(ascending=True, method='min')

        de_all = pd.DataFrame({c: d['D/E Ratio'].values for c, d in group_data.items()})
        de_quarterly_avg = de_all.mean(axis=1)
        de_quarterly_ranks = de_all.rank(axis=1, ascending=False, method='min')

        ltd_all = pd.DataFrame({c: d['長期負債比率(%)'].values for c, d in group_data.items()})
        ltd_quarterly_ranks = ltd_all.rank(axis=1, ascending=False, method='min')
        ltd_company_means = ltd_all.mean(axis=0)
        ltd_industry_avg = ltd_company_means.mean()
        ltd_industry_std = ltd_company_means.std()

        icr_all = pd.DataFrame({c: d['ICR'].values for c, d in group_data.items()})
        icr_quarterly_ranks = icr_all.rank(axis=1, ascending=True, method='min')

        cr_all = pd.DataFrame({c: d['流動比率(%)'].values for c, d in group_data.items()})
        cr_quarterly_ranks = cr_all.rank(axis=1, ascending=True, method='min')

        qr_all = pd.DataFrame({c: d['速動比率(%)'].values for c, d in group_data.items()})
        qr_quarterly_ranks = qr_all.rank(axis=1, ascending=True, method='min')

        ebitda_all = pd.DataFrame({c: d['EBITDA Margin'].values for c, d in group_data.items()})
        ebitda_quarterly_ranks = ebitda_all.rank(axis=1, ascending=True, method='min')
        ebitda_sd_series = pd.Series({c: d['EBITDA Margin'].std() for c, d in group_data.items()})
        ebitda_sd_ranks = ebitda_sd_series.rank(ascending=False, method='min')

        gm_all = pd.DataFrame({c: d['毛利率(%)'].values for c, d in group_data.items()})
        gm_quarterly_ranks = gm_all.rank(axis=1, ascending=True, method='min')
        gm_sd_series = pd.Series({c: d['毛利率(%)'].std() for c, d in group_data.items()})
        gm_sd_ranks = gm_sd_series.rank(ascending=False, method='min')

        gm_cum_growth_map = {}
        for c, d in group_data.items():
            vals = d['毛利率(%)'].values
            total_change = sum([(vals[i] - vals[i - 1]) / (abs(vals[i - 1]) if vals[i - 1] != 0 else 0.01) for i in
                                range(1, len(vals))])
            gm_cum_growth_map[c] = total_change

        gm_cum_growth_series = pd.Series(gm_cum_growth_map)
        gm_growth_rank_scores = gm_cum_growth_series.rank(ascending=True, method='min')
        gm_growth_display_rank = gm_cum_growth_series.rank(ascending=False, method='min')

        # --- 利潤率預計算修正 ---
        pm_all = pd.DataFrame({c: d['利潤率(%)'].values for c, d in group_data.items()})
        pm_quarterly_ranks = pm_all.rank(axis=1, ascending=True, method='min')

        pm_cum_growth_map = {c: sum([r for r in mod.calc_growth_rates(d['利潤率(%)'].values) if r is not None]) for c, d
                             in group_data.items()}
        pm_cum_growth_series = pd.Series(pm_cum_growth_map)
        # ascending=True: 成長總和越高，排名點數越高 (1 -> N)
        pm_growth_rank_scores = pm_cum_growth_series.rank(ascending=True, method='min')

        industry_pools[pos] = {
            'N': n_count,
            'ocf_growth_sum': ocf_g_series,
            'ocf_growth_rank': ocf_g_series.rank(ascending=True, method='min'),
            'ccc_m': pd.Series({c: d['CCC'].mean() for c, d in group_data.items()}),
            'ccc_s': pd.Series({c: d['CCC'].std() for c, d in group_data.items()}),
            'dr_quarterly_ranks_df': dr_quarterly_ranks,
            'dr_rank_sum': dr_quarterly_ranks.sum(axis=0),
            'dr_growth_sum_series': dr_growth_sum_series,
            'dr_g_sum_rank': dr_g_sum_rank,
            'de_avg_series': de_quarterly_avg,
            'de_ranks_df': de_quarterly_ranks,
            'ltd_ranks_df': ltd_quarterly_ranks,
            'ltd_ind_avg': ltd_industry_avg,
            'ltd_ind_std': ltd_industry_std,
            'icr_ranks_df': icr_quarterly_ranks,
            'cr_ranks_df': cr_quarterly_ranks,
            'qr_ranks_df': qr_quarterly_ranks,
            'ebitda_ranks_df': ebitda_quarterly_ranks,
            'ebitda_sd_ranks': ebitda_sd_ranks,
            'gm_ranks_df': gm_quarterly_ranks,
            'gm_cum_growth_series': gm_cum_growth_series,
            'gm_growth_rank_scores': gm_growth_rank_scores,
            'gm_growth_display_rank': gm_growth_display_rank,
            'gm_sd_ranks': gm_sd_ranks,
            'pm_ranks_df': pm_quarterly_ranks,
            'pm_cum_growth_series': pm_cum_growth_series,
            'pm_growth_rank_scores': pm_growth_rank_scores
        }

    matrix_rows = []
    for cid in sorted(temp_valid_data.keys()):
        pos = industry_map.get(cid)
        current_pool = industry_pools.get(pos)
        if not current_pool: continue

        N, df = current_pool['N'], temp_valid_data[cid]
        v = {col: df[col].values for col in
             ['OCF', 'FCF', 'CCC', '負債比率(%)', 'ICR', '毛利率(%)', 'EBITDA Margin', '利潤率(%)', '流動比率(%)',
              '速動比率(%)', 'D/E Ratio']}

        matrix_rows.append(
            {'Type': 'COMP', '指標': f"【{cid}】 產業地位：{pos}", **{p: '' for p in PERIOD_COLS}, '統計/加權分': '---'})

        # 2. 現金流管理評分
        cf_dynamic = mod.calc_dynamic_cashflow_management(v['OCF'], v['FCF'], N)
        quality_final_score = cf_dynamic[-1]
        o_g_sum = current_pool['ocf_growth_sum'].get(cid, 0)
        o_g_rank_score = current_pool['ocf_growth_rank'].get(cid, 0)
        o_g_score_scaled = round(o_g_rank_score * NUM_PERIODS, 3)
        o_g_display_rank = int(N - o_g_rank_score + 1)
        o_g_rates = [f"{round(r * 100, 1)}%" if r is not None else '-' for r in mod.calc_growth_rates(v['OCF'])]
        final_cashflow_management_score = round(quality_final_score * 0.6 + o_g_score_scaled * 0.4, 3)

        matrix_rows.append(
            {'Type': 'DATA', '指標': 'OCF 原始數據', **dict(zip(PERIOD_COLS, v['OCF'])), '統計/加權分': ""})
        matrix_rows.append(
            {'Type': 'DATA', '指標': 'FCF 原始數據', **dict(zip(PERIOD_COLS, v['FCF'])), '統計/加權分': ""})
        matrix_rows.append(
            {'Type': 'SCR', '指標': '└─ 營運品質評分 (OCF/FCF 懲罰演變)', **dict(zip(PERIOD_COLS, cf_dynamic)),
             '統計/加權分': f"最終品質分:{quality_final_score}"})
        matrix_rows.append(
            {'Type': 'DATA', '指標': f'OCF {NUM_PERIODS}季成長率 (%)', **dict(zip(PERIOD_COLS, o_g_rates)),
             '統計/加權分': f"成長總和:{round(o_g_sum * 100, 2)}% (第{o_g_display_rank}名, 得分:{o_g_score_scaled})"})
        matrix_rows.append({'Type': 'TOT', '指標': '★ 現金流管理加權總評分', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': final_cashflow_management_score})

        # 3. 經營效率
        ccc_vals = v['CCC']
        ccc_mean, ccc_std = ccc_vals.mean(), ccc_vals.std()
        ccc_m_display_rank = int(current_pool['ccc_m'].rank(ascending=True, method='min').get(cid, 0))
        ccc_m_scaled = (N - ccc_m_display_rank + 1) * NUM_PERIODS
        ccc_s_display_rank = int(current_pool['ccc_s'].rank(ascending=True, method='min').get(cid, 0))
        ccc_s_scaled = float((N - ccc_s_display_rank + 1) * NUM_PERIODS)
        ccc_weighted = round((ccc_m_scaled * 0.6 + ccc_s_scaled * 0.4), 1)

        matrix_rows.append({'Type': 'DATA', '指標': 'CCC 營運天數', **dict(zip(PERIOD_COLS, ccc_vals)),
                            '統計/加權分': f"平均:{round(ccc_mean, 1)} 標差:{round(ccc_std, 2)}"})
        matrix_rows.append({'Type': 'SCR', '指標': ' └─ CCC平均數組內排名與得分', **{p: '' for p in PERIOD_COLS},
                            '統計/加權分': f"排名:{ccc_m_display_rank} (得分:{ccc_m_scaled})"})
        matrix_rows.append({'Type': 'SCR', '指標': ' └─ CCC標差組內排名與得分', **{p: '' for p in PERIOD_COLS},
                            '統計/加權分': f"排名:{ccc_s_display_rank} (得分:{ccc_s_scaled})"})
        matrix_rows.append(
            {'Type': 'TOT', '指標': f'★ CCC經營效率評分-{NUM_PERIODS}季制', **{p: '---' for p in PERIOD_COLS},
             '統計/加權分': ccc_weighted})

        # 4. 財務結構 (負債比)
        dr_vals = v['負債比率(%)']
        dr_sum_val = current_pool['dr_rank_sum'].get(cid, 0)
        dr_g_rates_raw = mod.calc_growth_rates(dr_vals)
        dr_g_sum = current_pool['dr_growth_sum_series'].get(cid, 0)
        dr_g_rank = current_pool['dr_g_sum_rank'].get(cid, 0)
        dr_g_score_scaled = round(
            mod.get_rank_score_from_series(dr_g_sum, current_pool['dr_growth_sum_series'], False) * NUM_PERIODS, 1)
        debt_weighted = round((dr_sum_val * 0.4) + (dr_g_score_scaled * 0.6), 1)
        q_display = {PERIOD_COLS[i]: f"{int(s)}(第{int(N - s + 1)}名)" for i, s in
                     enumerate(current_pool['dr_quarterly_ranks_df'][cid])}

        matrix_rows.append(
            {'Type': 'DATA', '指標': '負債比率 (%)', **dict(zip(PERIOD_COLS, dr_vals)), '統計/加權分': ""})
        dr_g_rates_str = [f"{round(r * 100, 1)}%" if r is not None else '-' for r in dr_g_rates_raw]
        matrix_rows.append(
            {'Type': 'DATA', '指標': ' └─ 負債比季度變動率 (%)', **dict(zip(PERIOD_COLS, dr_g_rates_str)),
             '統計/加權分': f"變動總和:{round(dr_g_sum * 100, 2)}% (第{int(dr_g_rank)}名, 得分:{dr_g_score_scaled})"})
        matrix_rows.append(
            {'Type': 'SCR', '指標': ' └─ 每季組內排名總分', **q_display, '統計/加權分': f"排名總和:{int(dr_sum_val)}"})
        matrix_rows.append(
            {'Type': 'TOT', '指標': f'★ 財務結構評分-{NUM_PERIODS}季制', **{p: '---' for p in PERIOD_COLS},
             '統計/加權分': debt_weighted})

        # D/E Ratio
        de_vals = v['D/E Ratio']
        de_avg_ref = current_pool['de_avg_series'].values
        global_industry_avg = de_avg_ref.mean()
        de_rank_ref = current_pool['de_ranks_df'][cid].values
        low_de_count = 0
        high_de_count = 0
        final_de_q_scores, de_q_display = [], {}

        for i in range(NUM_PERIODS):
            p, q_val, q_ocf, base_score = PERIOD_COLS[i], de_vals[i], v['OCF'][i], de_rank_ref[i]
            penalty_reason, adj_score = "", base_score

            if q_val > global_industry_avg * 2.0:
                adj_score, penalty_reason = 1.0, "(>2x均值)"
                high_de_count += 1
            elif q_val < global_industry_avg * 0.5 and q_ocf < 0:
                adj_score, penalty_reason = 1.0, "(低DE&OCF負)"
                low_de_count += 1

            final_de_q_scores.append(round(adj_score, 2))
            q_rank_str = f"第{int(N - base_score + 1)}名"
            de_q_display[p] = f"{int(adj_score)}({q_rank_str}){penalty_reason}"

        matrix_rows.append({'Type': 'DATA', '指標': 'D/E Ratio (倍)', **dict(zip(PERIOD_COLS, de_vals)),
                            '統計/加權分': f"產業均值:{round(global_industry_avg, 2)}"})
        matrix_rows.append({'Type': 'SCR', '指標': ' └─ D/E組內每季排名分數', **de_q_display,
                            '統計/加權分': f"得分總和:{round(sum(final_de_q_scores), 1)}"})
        matrix_rows.append({'Type': 'TOT', '指標': '★ D/E 財務結構評分', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': round(sum(final_de_q_scores), 1)})

        # 6. 長期負債結構 (長債80/ICR20)
        ltd_rank_ref = current_pool['ltd_ranks_df'][cid].values
        ind_ltd_avg = current_pool.get('ltd_ind_avg', 0)
        ind_ltd_std = current_pool.get('ltd_ind_std', 0)
        icr_rank_ref = current_pool['icr_ranks_df'][cid].values
        icr_raw_vals = v['ICR']
        ltd_rank_display, icr_rank_display, ltd_valid_scores, icr_valid_scores = {}, {}, [], []

        for i in range(NUM_PERIODS):
            p = PERIOD_COLS[i]
            if not np.isnan(ltd_rank_ref[i]):
                ltd_rank_display[p] = f"{int(ltd_rank_ref[i])}(第{int(N - ltd_rank_ref[i] + 1)}名)"
                ltd_valid_scores.append(ltd_rank_ref[i])
            else:
                ltd_rank_display[p] = "0(資料缺失)"
            if not np.isnan(icr_rank_ref[i]) and not np.isnan(icr_raw_vals[i]):
                icr_rank_display[p] = f"{int(icr_rank_ref[i])}(第{int(N - icr_rank_ref[i] + 1)}名)"
                icr_valid_scores.append(icr_rank_ref[i])
            else:
                icr_rank_display[p] = "0(資料缺失)"

        ltd_norm_total = (sum(ltd_valid_scores) / len(ltd_valid_scores)) * NUM_PERIODS if ltd_valid_scores else 0.0
        icr_norm_total = (sum(icr_valid_scores) / len(icr_valid_scores)) * NUM_PERIODS if icr_valid_scores else 0.0
        individual_ltd_mean = np.nanmean(df['長期負債比率(%)'])
        is_ltd_abnormal = (individual_ltd_mean > (ind_ltd_avg + ind_ltd_std)) or (
                    individual_ltd_mean < (ind_ltd_avg - ind_ltd_std))
        ltd_penalty_msg = "(異常結構警告:x0.6)" if is_ltd_abnormal else ""
        if is_ltd_abnormal: ltd_norm_total *= 0.6

        matrix_rows.append(
            {'Type': 'DATA', '指標': '長期負債比率 (%)', **dict(zip(PERIOD_COLS, df['長期負債比率(%)'].values)),
             '統計/加權分': f"個體均:{round(individual_ltd_mean, 1)}% | 產業均:{round(ind_ltd_avg, 1)}% (±{round(ind_ltd_std, 2)})"})
        matrix_rows.append({'Type': 'SCR', '指標': ' └─ 長期負債比季度排名', **ltd_rank_display,
                            '統計/加權分': f"回補總分:{round(ltd_norm_total, 1)} {ltd_penalty_msg}"})
        matrix_rows.append({'Type': 'DATA', '指標': 'ICR (利息保障倍數)', **dict(zip(PERIOD_COLS, icr_raw_vals)),
                            '統計/加權分': f"平均:{round(np.nanmean(icr_raw_vals), 1)}"})
        matrix_rows.append({'Type': 'SCR', '指標': ' └─ ICR 季度排名', **icr_rank_display,
                            '統計/加權分': f"回補總分:{round(icr_norm_total, 1)}"})
        matrix_rows.append({'Type': 'TOT', '指標': '★ 長期負債結構最終評分', **{p: '' for p in PERIOD_COLS},
                            '統計/加權分': round((icr_norm_total * 0.2) + (ltd_norm_total * 0.8), 1)})

        # 7. 短期償債能力
        cr_vals, qr_vals = v['流動比率(%)'], v['速動比率(%)']
        cr_rank_ref, qr_rank_ref = current_pool['cr_ranks_df'][cid].values, current_pool['qr_ranks_df'][cid].values
        tot_liq_display, final_liq_q_scores = {}, []
        ever_triggered_liquidity_penalty = False

        for i in range(NUM_PERIODS):
            r_cr, r_qr, v_cr, v_qr = cr_rank_ref[i], qr_rank_ref[i], cr_vals[i], qr_vals[i]
            base = (r_cr * 0.6) + (r_qr * 0.4)
            penalty, score = "", round(base, 2)
            if v_cr < 100:
                score, penalty = 1.0, "流動<100%"
                ever_triggered_liquidity_penalty = True
            elif v_qr < 100:
                score, penalty = round(base * 0.7, 2), "速動<100%"
                ever_triggered_liquidity_penalty = True

            final_liq_q_scores.append(max(score, 1.0))
            tot_liq_display[PERIOD_COLS[i]] = f"{score}({penalty})" if penalty else str(score)
        liq_base_sum = sum(final_liq_q_scores)
        liq_final_score = liq_base_sum * (0.9 ** low_de_count)
        bonus_msg = ""
        if high_de_count > 0 and not ever_triggered_liquidity_penalty:
            liq_final_score *= 1.2
            bonus_msg = " [槓桿獎勵x1.2]"
        penalty_msg = f" [低效懲罰x0.9^{low_de_count}]" if low_de_count > 0 else ""

        matrix_rows.append(
            {'Type': 'DATA', '指標': '流動比率 (%)', **dict(zip(PERIOD_COLS, cr_vals)), '統計/加權分': ""})
        matrix_rows.append({'Type': 'SCR', '指標': ' └─ 流動比組內每季排名',
                            **{PERIOD_COLS[i]: f"{int(cr_rank_ref[i])}(第{int(N - cr_rank_ref[i] + 1)}名)" for i in
                               range(NUM_PERIODS)}, '統計/加權分': ""})
        matrix_rows.append(
            {'Type': 'DATA', '指標': '速動比率 (%)', **dict(zip(PERIOD_COLS, qr_vals)), '統計/加權分': ""})
        matrix_rows.append({'Type': 'SCR', '指標': ' └─ 速動比組內每季排名',
                            **{PERIOD_COLS[i]: f"{int(qr_rank_ref[i])}(第{int(N - qr_rank_ref[i] + 1)}名)" for i in
                               range(NUM_PERIODS)}, '統計/加權分': ""})

        matrix_rows.append({
            'Type': 'TOT',
            '指標': '★ 短期償債能力最終評分',
            **tot_liq_display,
            '統計/加權分': f"{round(liq_final_score, 1)}{penalty_msg}{bonus_msg} (原始:{round(liq_base_sum, 1)})"
        })

        # 8. EBITDA Margin
        eb_vals = v['EBITDA Margin']
        eb_rank_ref = current_pool['ebitda_ranks_df'][cid].values
        eb_sd_rank_score = current_pool['ebitda_sd_ranks'].get(cid, 0)
        eb_sd_score = round(float(eb_sd_rank_score * NUM_PERIODS), 1)
        eb_total = round((sum(eb_rank_ref) * 0.7) + (eb_sd_score * 0.3), 1)
        matrix_rows.append({'Type': 'DATA', '指標': 'EBITDA Margin', **dict(zip(PERIOD_COLS, eb_vals)),
                            '統計/加權分': f"標差:{round(eb_vals.std(), 4)} 排名:{int(N - eb_sd_rank_score + 1)} (得分:{eb_sd_score})"})
        matrix_rows.append({'Type': 'SCR', '指標': ' └─ 每季組內排名分數',
                            **{PERIOD_COLS[i]: f"{int(eb_rank_ref[i])}(第{int(N - eb_rank_ref[i] + 1)}名)" for i in
                               range(NUM_PERIODS)}, '統計/加權分': f"排名總和:{int(sum(eb_rank_ref))}"})
        matrix_rows.append(
            {'Type': 'TOT', '指標': '★ 獲利能力最終評分', **{p: '---' for p in PERIOD_COLS}, '統計/加權分': eb_total})

        # 9. 毛利率
        gm_vals = v['毛利率(%)']
        gm_rank_ref = current_pool['gm_ranks_df'][cid].values
        gm_cum_growth = current_pool['gm_cum_growth_series'].get(cid, 0)
        gm_g_score_scaled = round(current_pool['gm_growth_rank_scores'].get(cid, 0) * NUM_PERIODS, 1)
        base_gm_score = (gm_g_score_scaled * 0.7) + (sum(gm_rank_ref) * 0.3)
        final_gm = round(base_gm_score * 0.7, 1) if gm_cum_growth <= 0 else round(base_gm_score, 1)
        matrix_rows.append({'Type': 'DATA', '指標': '毛利率 (%)', **dict(zip(PERIOD_COLS, gm_vals)),
                            '統計/加權分': f"平均:{round(gm_vals.mean(), 2)}%"})
        matrix_rows.append({'Type': 'SCR', '指標': ' └─ 每季毛利率組內排名',
                            **{PERIOD_COLS[i]: f"{int(gm_rank_ref[i])}(第{int(N - gm_rank_ref[i] + 1)}名)" for i in
                               range(NUM_PERIODS)}, '統計/加權分': f"排名總分:{int(sum(gm_rank_ref))}"})
        matrix_rows.append({'Type': 'DATA', '指標': ' └─ 每季毛利變動率 (%)', **{PERIOD_COLS[i]: (
            f"{round(((gm_vals[i] - gm_vals[i - 1]) / abs(gm_vals[i - 1] if gm_vals[i - 1] != 0 else 0.01)) * 100, 2)}%" if i > 0 else "-")
                                                                                 for i in range(NUM_PERIODS)},
                            '統計/加權分': f"累積變動:{round(gm_cum_growth * 100, 2)}% (得分:{gm_g_score_scaled})"})
        matrix_rows.append({'Type': 'TOT', '指標': '★ 毛利率最終評分 (EV 競爭力)', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': f"{final_gm} {'(累積衰退:打7折)' if gm_cum_growth <= 0 else ''}"})

        # 10. 利潤率 (修正後：50/50 權重，排名總和 + 成長總和排名得分)
        pm_vals = v['利潤率(%)']
        pm_rank_ref = current_pool['pm_ranks_df'][cid].values
        pm_q_scores, pm_q_rank_display = [], {}

        for i in range(NUM_PERIODS):
            # 虧損懲罰與排名邏輯
            s, txt = (1.0, "1(虧損懲罰)") if pm_vals[i] < 0 else (pm_rank_ref[i],
                                                                  f"{int(pm_rank_ref[i])}(第{int(N - pm_rank_ref[i] + 1)}名)")
            pm_q_scores.append(s)
            pm_q_rank_display[PERIOD_COLS[i]] = txt

        # 季度排名總得分 (30% -> 修正為 50%)
        pm_rank_sum = sum(pm_q_scores)

        # 成長率計算與累積總和 (從 pool 獲取組內排名)
        pm_g_rates_raw = mod.calc_growth_rates(pm_vals)
        pm_g_rates_str = [f"{round(r * 100, 2)}%" if r is not None else "-" for r in pm_g_rates_raw]
        pm_g_cum_sum = current_pool['pm_cum_growth_series'].get(cid, 0)
        pm_g_rank_raw = current_pool['pm_growth_rank_scores'].get(cid, 0)  # 這是組內排名點數 (1~N)
        pm_g_score_scaled = round(pm_g_rank_raw * NUM_PERIODS, 1)

        # 最終加權評分 (50% 季度排名 + 50% 累積成長排名)
        final_pm_score = round((pm_rank_sum * 0.5) + (pm_g_score_scaled * 0.5), 1)

        matrix_rows.append({'Type': 'DATA', '指標': '利潤率 (%)', **dict(zip(PERIOD_COLS, pm_vals)),
                            '統計/加權分': f"平均:{round(pm_vals.mean(), 2)}%"})

        matrix_rows.append({'Type': 'SCR', '指標': ' └─ 利潤率組內每季排名分數', **pm_q_rank_display,
                            '統計/加權分': f"排名總分:{round(pm_rank_sum, 1)} (權重 50%)"})

        matrix_rows.append({
            'Type': 'DATA',
            '指標': ' └─ 利潤率季度成長率 (%)',
            **dict(zip(PERIOD_COLS, pm_g_rates_str)),
            '統計/加權分': f"成長總和:{round(pm_g_cum_sum * 100, 2)}% (排名第{int(N - pm_g_rank_raw + 1)}名, 得分:{pm_g_score_scaled}, 權重 50%)"
        })

        matrix_rows.append({'Type': 'TOT', '指標': '★ 利潤率最終評分', **{p: '---' for p in PERIOD_COLS},
                            '統計/加權分': final_pm_score})

        matrix_rows.append({k: '' for k in ['Type', '指標', *PERIOD_COLS, '統計/加權分']})

    # ========= 輸出部分 (樣式完全保留) =========
    report_df = pd.DataFrame(matrix_rows)
    excel_path = OUTPUT_DIR / "modular_strategy_matrix_final.xlsx"
    writer = pd.ExcelWriter(excel_path, engine='xlsxwriter')
    report_df.to_excel(writer, index=False, sheet_name='財務評分矩陣')
    workbook, worksheet = writer.book, writer.sheets['財務評分矩陣']

    fmt_comp = workbook.add_format({'bold': True, 'font_color': '#FF0000', 'bg_color': '#FCE4D6', 'border': 1})
    fmt_scr = workbook.add_format(
        {'italic': True, 'font_color': '#0070C0', 'bg_color': '#E2EFDA', 'border': 1, 'align': 'center'})
    fmt_data = workbook.add_format({'border': 1})
    fmt_tot = workbook.add_format({'bold': True, 'bg_color': '#FFD966', 'border': 2, 'align': 'center'})

    for i, row in enumerate(matrix_rows):
        idx = i + 1
        t = row.get('Type')
        if t == 'COMP':
            worksheet.set_row(idx, None, fmt_comp)
        elif t == 'SCR':
            worksheet.set_row(idx, None, fmt_scr)
        elif t == 'DATA':
            worksheet.set_row(idx, None, fmt_data)
        elif t == 'TOT':
            worksheet.set_row(idx, None, fmt_tot)

    worksheet.set_column('A:A', 0)
    worksheet.set_column('B:B', 50)
    worksheet.set_column(2, 2 + NUM_PERIODS - 1, 12)
    worksheet.set_column(2 + NUM_PERIODS, 2 + NUM_PERIODS, 45)
    worksheet.freeze_panes(1, 2)
    writer.close()
    print(f"🏆 矩陣報表已產出：{excel_path.name}")


if __name__ == "__main__":
    main()
