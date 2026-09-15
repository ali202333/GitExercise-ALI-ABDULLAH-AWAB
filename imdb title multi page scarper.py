import asyncio
import json
import os
from playwright.async_api import async_playwright

TOTAL_MOVIES_TO_SCRAPE = 500  # Set your target total here

async def run():
    async with async_playwright() as p:
        # 1. Launch Browser
        browser = await p.chromium.launch(
            headless=True,
            args=["--disable-blink-features=AutomationControlled"]
        )
        
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
            locale="en-US",
            viewport={"width": 1920, "height": 1080}
        )
        
        page = await context.new_page()
        unique_urls = set()

        print(f"Navigating to IMDb to collect {TOTAL_MOVIES_TO_SCRAPE} unique title URLs...")
        
        # Sort feature films by user rating with >10,000 votes
        search_url = "https://www.imdb.com/search/title/?title_type=feature&num_votes=10000,&sort=user_rating,desc"
        await page.goto(search_url, wait_until="domcontentloaded")

        # Wait for the first set of movie links to render
        await page.wait_for_selector("a.ipc-title-link-wrapper", timeout=15000)

        # 2. Scroll and Click "50 More" to accumulate links safely
        while len(unique_urls) < TOTAL_MOVIES_TO_SCRAPE:
            # Scroll down to trigger lazy loading of elements
            await page.evaluate("window.scrollTo(0, document.body.scrollHeight)")
            await page.wait_for_timeout(1000)

            # Extract current visible title links
            elements = await page.query_selector_all("a.ipc-title-link-wrapper")
            for el in elements:
                try:
                    href = await el.get_attribute("href")
                    if href and "/title/tt" in href:
                        clean_url = f"https://www.imdb.com{href.split('?')[0]}"
                        unique_urls.add(clean_url)
                except Exception:
                    continue

            print(f"Collected {len(unique_urls)} / {TOTAL_MOVIES_TO_SCRAPE} unique URLs...")

            if len(unique_urls) >= TOTAL_MOVIES_TO_SCRAPE:
                break

            # Look for the '50 more' button and click it safely
            try:
                more_button = page.locator("button.ipc-see-more__button")
                if await more_button.is_visible():
                    await more_button.click()
                    await page.wait_for_timeout(2000)  # Wait for new titles to load into DOM
                else:
                    print("No more results available.")
                    break
            except Exception:
                # If the button isn't immediately clickable, retry scrolling once before exiting
                await page.evaluate("window.scrollTo(0, document.body.scrollHeight)")
                await page.wait_for_timeout(1500)
                if not await page.locator("button.ipc-see-more__button").is_visible():
                    break

        movie_urls = list(unique_urls)[:TOTAL_MOVIES_TO_SCRAPE]
        print(f"\nGathered {len(movie_urls)} unique URLs. Starting detail extraction...\n")

        # 3. Extract Details from Each Page
        scraped_data = []

        for idx, url in enumerate(movie_urls, 1):
            try:
                print(f"[{idx}/{len(movie_urls)}] Scraping: {url}")
                await page.goto(url, wait_until="domcontentloaded", timeout=20000)
                
                # Title
                title_el = await page.query_selector("h1[data-testid='hero__pageTitle']")
                title = (await title_el.inner_text()).strip() if title_el else None

                # Rating
                rating_el = await page.query_selector("span.sc-d541859f-1, div[data-testid='hero-rating-bar__aggregate-rating__score'] span")
                rating = (await rating_el.inner_text()).strip() if rating_el else None

                # Genres
                genre_els = await page.query_selector_all("a.ipc-chip span.ipc-chip__text")
                genres = [(await g.inner_text()).strip() for g in genre_els if g]

                scraped_data.append({
                    "id": idx,
                    "title": title,
                    "rating": rating,
                    "genres": genres,
                    "movie_url": url
                })

            except Exception as e:
                print(f"Failed to scrape {url}: {e}")

        # 4. Save directly in the script directory
        script_dir = os.path.dirname(os.path.abspath(__file__))
        output_path = os.path.join(script_dir, "output.json")

        with open(output_path, "w", encoding="utf-8") as f:
            json.dump(scraped_data, f, indent=4)

        print(f"\nDone! Saved {len(scraped_data)} unique movie records to:\n{output_path}")
        await browser.close()

if __name__ == "__main__":
    asyncio.run(run())