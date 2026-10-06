const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const bridgeCalls = [];
const element = {
  value: '', style: {}, classList: { add() {}, remove() {} },
  addEventListener() {}, removeEventListener() {}
};
const context = {
  window: { location: {} },
  document: { getElementById() { return element; }, addEventListener() {} },
  navigator: { userAgent: 'SecurityFixture' },
  getId() { return element; },
  Hammer: function () { this.on = function () { return this; }; },
  $() { return { val() { return ''; }, on() {}, html() {}, text() {}, css() {} }; },
  WeixinJSBridge: { invoke(name, data) { bridgeCalls.push({ name, data }); } },
  mqq: { tenpay: { pay(data) { bridgeCalls.push({ name: 'qq', data }); } } },
  tips: { show() {} }
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../paypage/js/pay.js'), 'utf8'),
  context, { timeout: 1000 });

context.WxpayJsPay(JSON.stringify({ appId: 'fixture-app', nonceStr: 'fixture-nonce' }));
assert.equal(bridgeCalls[0].data.appId, 'fixture-app');
context.QQJsPay(JSON.stringify({ tokenId: 'fixture-token', appInfo: 'fixture-info' }));
assert.equal(bridgeCalls[1].data.tokenId, 'fixture-token');

for (const name of ['WxpayJsPay', 'QQJsPay']) {
  const before = bridgeCalls.length;
  assert.throws(() => context[name]('({"tokenId":"x","appId":(window.fixtureExecuted=true)})'));
  assert.equal(context.window.fixtureExecuted, undefined);
  assert.equal(bridgeCalls.length, before);
}
console.log('PASS: valid payment JSON works; executable payment text is rejected.');
