const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

const fixture = JSON.parse(fs.readFileSync(path.join(process.env.BALIKOVNA_TEST_SITE, 'fixture-data.json'), 'utf8'));

for (const checkout of ['block', 'classic']) {
  for (const packages of [1, 2]) {
    test(`${checkout} checkout stores ${packages} independent shipment selections`, async ({ page, context, baseURL }) => {
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      if (packages === 2) {
        await page.setViewportSize({ width: 390, height: 844 });
        await context.addCookies([{ name: 'balikovna_test_packages', value: '2', url: baseURL }]);
      }
      await page.route('https://b2c.cpost.cz/locations/**', route => route.fulfill({
        contentType: 'text/html',
        body: `<html><body><button id="first">Praha 10</button><button id="second">Brno</button><script>
          for (const [button,id,name] of [['first','B10000','Praha 10'],['second','B60200','Brno']]) {
            document.getElementById(button).onclick = () => parent.postMessage({message:'pickerResult', point:{id,name,type:'BALIKOVNY'}}, ${JSON.stringify(baseURL)});
          }
        </script></body></html>`,
      }));
      const pageId = checkout === 'block' ? fixture.block_id : fixture.classic_id;
      await page.goto(`/?page_id=${pageId}&add-to-cart=${fixture.product_id}&quantity=${packages}`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('.balikovna-open')).toHaveCount(packages);
      const values = checkout === 'block' ? {
        email: 'e2e@example.test', 'shipping-first_name': 'E2E', 'shipping-last_name': 'Customer', 'shipping-address_1': 'Testovaci 1', 'shipping-city': 'Praha', 'shipping-postcode': '10000', 'shipping-phone': '+420700000001',
      } : {
        billing_email: 'e2e@example.test', billing_first_name: 'E2E', billing_last_name: 'Customer', billing_address_1: 'Testovaci 1', billing_city: 'Praha', billing_postcode: '10000', billing_phone: '+420700000001',
      };
      for (const [id, value] of Object.entries(values)) await page.locator(`#${id}`).fill(value);
      await page.locator(checkout === 'block' ? '#shipping-phone' : '#billing_phone').blur();
      const submit = page.locator(checkout === 'block' ? '.wc-block-components-checkout-place-order-button' : '#place_order');
      await submit.click();
      await expect(page.locator('body')).toContainText('Prosím zvolte výdejní místo pro každý balík.');
      for (let index = 0; index < packages; index++) {
        await page.locator('.balikovna-open').nth(index).click();
        await expect(page.locator('.balikovna-modal')).toBeVisible();
        await page.evaluate(() => window.postMessage({ message: 'pickerResult', point: { id: 'B99999', name: 'Untrusted' } }, location.origin));
        await expect(page.locator('.balikovna-modal')).toBeVisible();
        await page.frameLocator('.balikovna-modal iframe').locator(index === 0 ? '#first' : '#second').click();
        await expect(page.locator('.balikovna-modal')).toHaveCount(0);
        await expect(page.locator('.balikovna-selected').nth(index)).toContainText(index === 0 ? 'Praha 10' : 'Brno');
      }
      const phone = page.locator(checkout === 'block' ? '#shipping-phone' : '#billing_phone');
      await phone.fill('');
      await phone.pressSequentially('+420700000001');
      await phone.blur();
      await expect(phone).toHaveValue('+420700000001');
      expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
      let checkoutResponse;
      await page.route(url => checkout === 'block'
        ? (url.searchParams.get('rest_route') || url.pathname).endsWith('/wc/store/v1/checkout')
        : url.searchParams.get('wc-ajax') === 'checkout', async route => {
        if (route.request().method() !== 'POST') return route.continue();
        const response = await route.fetch();
        checkoutResponse = { ok: response.ok(), body: await response.text() };
        await route.fulfill({ response });
      });
      await submit.click();
      await expect.poll(() => checkoutResponse, { message: 'Checkout API response' }).toBeDefined();
      expect(checkoutResponse.ok, checkoutResponse.body).toBe(true);
      const checkoutResult = JSON.parse(checkoutResponse.body);
      expect(checkout === 'block' ? checkoutResult.payment_result?.payment_status : checkoutResult.result, checkoutResponse.body).toBe('success');
      await page.waitForURL(url => url.searchParams.has('order-received'), { timeout: 45000, waitUntil: 'domcontentloaded' });
      const orderId = new URL(page.url()).searchParams.get('order-received');
      const result = JSON.parse(execFileSync(process.env.BALIKOVNA_PHP_BINARY || 'php', [
        ...JSON.parse(process.env.BALIKOVNA_PHP_ARGS || '[]'),
        path.join(__dirname, 'verify-order.php'), orderId,
      ], { encoding: 'utf8', env: process.env }));
      expect(result.status).toBe('processing');
      expect(result.shipments.map(shipment => shipment.point)).toEqual(packages === 1 ? ['B10000'] : ['B10000', 'B60200']);
      for (const shipment of result.shipments) {
        expect(Number(shipment.weight)).toBe(2);
        expect(Number(shipment.contents)).toBe(100);
        expect(shipment.limit).toBe(15);
      }
      expect(Array.isArray(result.csv)).toBe(true);
      expect(result.csv[0][7]).toBe(String(packages * 179));
      if (packages === 2) expect(result.csv[1][7]).toBe('');
      expect(result.errors).toEqual([]);
      expect(errors).toEqual([]);
    });
  }
}