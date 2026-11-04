import assert from 'node:assert'
import fs from 'node:fs'
import path from 'node:path'
import { compileScript, compileTemplate, parse } from 'vue/compiler-sfc'

const dir = path.resolve('src/components')
const expected = [
  'BadgeLencana.vue',
  'BadgeStatus.vue',
  'FormField.vue',
  'InputJumlah.vue',
  'InputKode.vue',
  'Memuat.vue',
  'ModalKonfirmasi.vue',
  'NotifikasiToast.vue',
  'PesanGalat.vue',
  'StatusKosong.vue',
  'StrukCetak.vue',
  'TombolBesar.vue',
]

const files = fs.readdirSync(dir).filter((f) => f.endsWith('.vue'))

assert.strictEqual(
  files.length,
  expected.length,
  `Jumlah komponen tidak sesuai: ${files.length} vs ${expected.length}`,
)

for (const name of expected) {
  assert.ok(files.includes(name), `Komponen ${name} tidak ditemukan`)
  const content = fs.readFileSync(path.join(dir, name), 'utf-8')
  const { descriptor, errors } = parse(content)
  assert.strictEqual(errors.length, 0, `Parse error pada ${name}: ${errors[0]?.message}`)

  if (descriptor.scriptSetup) {
    compileScript(descriptor, { id: name })
  }
  if (descriptor.template) {
    const result = compileTemplate({
      id: name,
      filename: name,
      source: descriptor.template.content,
    })
    assert.strictEqual(
      result.errors.length,
      0,
      `Template compile error pada ${name}: ${result.errors[0]?.message}`,
    )
  }
}

// ponytail: check parse/compile SFC statis; pasang vitest + @vue/test-utils saat butuh uji interaksi DOM
console.log('Semua 12 komponen lulus validasi sintaks dan kompilasi SFC.')
