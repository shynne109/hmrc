<?php

namespace HMRC\PAYE;

use XMLWriter;

/**
 * Represents a single company car benefit (PAYE RTI FPS Payment/Benefits/Car).
 *
 * NOTE: Class name intentionally matches user request spelling (CarBenefits).
 * Fields (all scalar unless nested array noted):
 *  - make (string)
 *  - firstRegd (Y-m-d)
 *  - co2 (int|string)
 *  - fuel (string) fuel type code
 *  - amendment (bool) -> yes|no
 *  - price (float)
 *  - availFrom (Y-m-d)
 *  - cashEquiv (float)
 *  - zeroEmissionsMileage (int, optional)
 *  - id (string, optional) -> ID element
 *  - availTo (Y-m-d, optional)
 *  - freeFuel (array optional: provided (Y-m-d), cashEquiv (float), withdrawn (Y-m-d optional))
 */
class CarBenefits
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function validate(): array
    {
        $e = [];
        $req = ['make','firstRegd','co2','fuel','amendment','price','availFrom','cashEquiv'];
        foreach ($req as $k) {
            if (!isset($this->data[$k])) { $e[] = $k . ' missing'; }
        }
        foreach (['firstRegd','availFrom','availTo'] as $d) {
            if (isset($this->data[$d]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->data[$d])) {
                $e[] = $d . ' invalid date';
            }
        }
        if (!is_bool($this->data['amendment'] ?? null)) { $e[] = 'amendment must be bool'; }
        if (isset($this->data['freeFuel'])) {
            $ff = $this->data['freeFuel'];
            if (!isset($ff['provided'], $ff['cashEquiv'])) { $e[] = 'freeFuel.provided & freeFuel.cashEquiv required'; }
            if (isset($ff['provided']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ff['provided'])) { $e[] = 'freeFuel.provided invalid date'; }
            if (isset($ff['withdrawn']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ff['withdrawn'])) { $e[] = 'freeFuel.withdrawn invalid date'; }
        }
        return $e;
    }

    public function writeXml(XMLWriter $xw): void
    {
        // Assume validation performed upstream
        $d = $this->data;
        $xw->startElement('Car');
        $xw->writeElement('Make', $d['make']);
        $xw->writeElement('FirstRegd', $d['firstRegd']);
        $xw->writeElement('CO2', (string)$d['co2']);
        if (isset($d['zeroEmissionsMileage'])) {
            $xw->writeElement('ZeroEmissionsMileage', (int)$d['zeroEmissionsMileage']);
        }
        $xw->writeElement('Fuel', $d['fuel']);
        if (!empty($d['id'])) {
            $xw->writeElement('ID', $d['id']);
        }
        $xw->writeElement('Amendment', !empty($d['amendment']) ? 'yes' : 'no');
        $xw->writeElement('Price', number_format($d['price'], 2, '.', ''));
        $xw->writeElement('AvailFrom', $d['availFrom']);
        $xw->writeElement('CashEquiv', number_format($d['cashEquiv'], 2, '.', ''));
        if (!empty($d['availTo'])) {
            $xw->writeElement('AvailTo', $d['availTo']);
        }
        if (!empty($d['freeFuel'])) {
            $ff = $d['freeFuel'];
            if (isset($ff['provided'], $ff['cashEquiv'])) {
                $xw->startElement('FreeFuel');
                $xw->writeElement('Provided', $ff['provided']);
                $xw->writeElement('CashEquiv', number_format($ff['cashEquiv'], 2, '.', ''));
                if (!empty($ff['withdrawn'])) {
                    $xw->writeElement('Withdrawn', $ff['withdrawn']);
                }
                $xw->endElement();
            }
        }
        $xw->endElement(); // Car
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
