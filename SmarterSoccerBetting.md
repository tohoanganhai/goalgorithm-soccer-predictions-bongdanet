## **Intro: Smarter Soccer Betting – Why xG and Poisson?**

Let’s get one thing straight: this isn’t a magic system, and it’s not tested. The point of this PDF is to show you how a simple model works, and how you can build it yourself in Google Sheets—even if you’ve never done it before. It’s about thinking like a modeler, and seeing how powerful a few formulas can be for your day-to-day betting.

You’ll learn where to get soccer stats, how to turn them into predictions, and how to use Google Sheets to do the heavy lifting. If you can copy, paste, and follow basic instructions, you can do this.

Ready to actually see the numbers behind the game? Let’s go.

---

## **2\. What You’ll Need (Seriously, Just the Basics)**

* **A Google Account.** (If you don’t have one, go [togmail.co](http://togmail.com)m and sign up free.)

* **Google Sheets.** Go [tosheets.google.co](http://tosheets.google.com)m, hit “Blank” to make a new sheet.

* **Internet.** You need to grab some stats, but you don’t need to install anything.

* **Thirty Minutes.** Yes, really.

* **A Brain, Not a Math Degree.** If you can use a calculator and copy formulas, you’re good.

---

## **3\. How to Get and Import xG/xGA Data Into Google Sheets**

### **Step-by-Step:**

1. **Open this URL:**  
   [https://fbref.com/en/comps/22/Major-League-Soccer-Stat](https://fbref.com/en/comps/22/Major-League-Soccer-Stats)s  
2. **Scroll down to the table called “Squad Standard Stats.”**

   * This has xG per 90 for each team.

3. **Find “Squad Standard Opponent Stats.”**

   * This has xGA per 90 for each team (how many expected goals they allow).

**TIP:** “Per 90” means “per full match.” That way, stats are fair for everyone.

4. **Highlight the whole table with your mouse.**

   * Start at the top left, drag to the bottom right.

5. **Right-click and Copy** (or Ctrl+C).

6. **Go to your Google Sheet, pick a blank tab, right-click cell A1, and “Paste special” → “Paste values only.”**

   * You may get extra rows or columns—just delete them so it’s tidy.

   * Repeat for both tables (one for xG, one for xGA). Use different tabs if you want, or just paste side by side.

**Result:**  
 You should see all the teams, their xG per 90 (attack), and xGA per 90 (defense).

---

## **4\. Understanding xG, xGA, and Why “Per 90” Matters**

* **xG (expected goals):** How many goals a team *should* score based on the quality of their shots.

* **xGA (expected goals against):** How many goals a team *should* allow, based on the shots they let the other team take.

* **Per 90:** Makes it fair. All teams get compared as if they played the same minutes.

If you see “xG/90” or “xGA/90,” that’s the column you want.  
**If you don’t, check for a column called just “xG” or “xGA,” and divide by games played.**

---

## **5\. Calculating League Averages (Simple Math, No Stress)**

You want to see how each team compares to the average.

**Here’s how:**

1. In Google Sheets, select all the “xG per 90” numbers for every team.

At the bottom of that column, type:

\=AVERAGE(B2:B29)

2.  *(Replace “B2:B29” with the actual range in your sheet.)*

3. Do the same for “xGA per 90.”

* **Write these averages down or highlight them. You’ll use them in the next step.**

---

## **6\. Calculating Attack and Defense Strengths for Each Team**

You want to see if a team is better or worse than average at scoring or defending.

**Add two columns to your sheet:**

* “Attack Strength”

* “Defense Strength”

**Formulas:**

**Attack Strength:**  
 In the new column, type for the first team:

\= \[Their xG per 90 cell\] / \[League Average xG per 90\]

*  *(Example: \=B2/$B$30)*

**Defense Strength:**

\= \[Their xGA per 90 cell\] / \[League Average xGA per 90\]

*  *(Example: \=C2/$C$30)*

* **Copy these formulas down the column for every team.**

**Result:**

* Over 1 \= above average

* Under 1 \= below average

---

## **7\. Predicting Expected Goals for a Matchup**

Say you want to predict Team A vs. Team B.

* **Home Team Expected Goals:**  
   Home Team’s Attack Strength × Away Team’s Defense Strength × League Average xG per 90

* **Away Team Expected Goals:**  
   Away Team’s Attack Strength × Home Team’s Defense Strength × League Average xG per 90

**How to do this in Google Sheets:**

* Make a new sheet/tab called “Matchup.”

* In cell A2, type Home Team’s name.

* In B2, type Away Team’s name.

* In C2, use a formula to “lookup” their Attack Strength (use VLOOKUP or just copy-paste).

* In D2, “lookup” their Defense Strength.

In E2, do the math:

\= \[Home Attack Strength\] *\[Away Defense Strength\]* \[League Avg xG per 90\]

In F2:

\= \[Away Attack Strength\] *\[Home Defense Strength\]* \[League Avg xG per 90\]

**Now you have predicted goals for each team\!**

---

## **8\. Using the Poisson Distribution for Goal Probabilities**

This is the math-y bit, but Sheets makes it easy.

* You want the chance each team scores 0, 1, 2, 3, 4, 5+ goals.

**In Google Sheets:**

* For Home Team, in a new table, label columns 0, 1, 2, 3, 4, 5\.

In the first row under 0 goals, use:

\=POISSON.DIST(0, \[Home Expected Goals cell\], FALSE)

For 1 goal:

\=POISSON.DIST(1, \[Home Expected Goals cell\], FALSE)

* Repeat up to 5 goals.

* **Do the same for the Away Team.**

**This gives you the probability of each exact goal count for both teams.**

---

## **9\. Building a Score Grid (The Magic Part)**

You want to know the odds of a specific score (like 2-1).

**How? Multiply the probability for each team:**

* In a new grid, label Home goals (0-5) down the side and Away goals (0-5) across the top.

In each cell, use:

\= \[Home Poisson probability\] \* \[Away Poisson probability\]

* For 2-1:

  * Home’s “2” goal probability × Away’s “1” goal probability.

**Sum all the boxes where Home \> Away for home win, equal for draw, Away \> Home for away win.**

**You now have model probabilities for every result—done in Google Sheets.**

---

## **10\. How to Use This Model in Real Life**

* **Compare your model’s odds to bookmaker odds.**

* **Look for value:** If your model thinks Home has a 45% chance, but bookies offer odds that only pay out at 35%, you might have an edge.

* **Don’t bet blindly.** This is a simple model. Upsets, red cards, injuries, weird weather—none of that is in here.

* **Use it as a learning tool.** The main thing: you’re thinking in probabilities, not gut feelings.

---

## **11\. Bonus: Tweaks and Upgrades (If You’re Feeling Bold)**

* Use separate stats for home and away matches.

* Try using only the last 5 games for form.

* Manually adjust for missing star players or new coaches.

* Test in other leagues—just grab xG/xGA from the same place.

