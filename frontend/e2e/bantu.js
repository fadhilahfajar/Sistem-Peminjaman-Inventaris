import { readFileSync, writeFileSync } from 'node:fs'

export const STATE = 'test-results/state.json'

export function bacaState() {
  try {
    return JSON.parse(readFileSync(STATE, 'utf8'))
  } catch {
    return {}
  }
}

export function tulisState(obj) {
  writeFileSync(STATE, JSON.stringify(obj, null, 2))
}

export async function csfrToken(page) {
  await page.request.get('/sanctum/csrf-cookie')
  const cookies = await page.context().cookies()
  const t = cookies.find((c) => c.name === 'XSRF-TOKEN')
  return encodeURIComponent(t?.value ?? '')
}

export async function postApi(
  page,
  url,
  data = {},
) {
  const token = await csfrToken(page)
  const res = await page.request.post(url, {
    data,
    headers: { 'X-XSRF-TOKEN': token, Accept: 'application/json' },
  })
  const body = await res.json().catch(() => ({}))
  return { status: res.status(), body }
}

export function kredensialAdmin() {
  const username = process.env.UJI_ADMIN_USERNAME
  const password = process.env.UJI_ADMIN_PASSWORD
  if (!username || !password) return null
  return { username, password }
}
